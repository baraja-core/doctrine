<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\Doctrine\DBAL\ConnectionFactory;
use Baraja\Doctrine\DBAL\Events\ContainerAwareEventManager;
use Baraja\Doctrine\DBAL\Events\DebugEventManager;
use Baraja\Doctrine\DBAL\Tracy\BlueScreen\DbalBlueScreen;
use Baraja\Doctrine\DBAL\Tracy\QueryPanel\QueryPanel;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\SQLite3Cache;
use Doctrine\Common\EventManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\Portability\Connection as PortabilityConnection;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Validators;

final class DatabaseExtension extends CompilerExtension
{
	public const TAG_DOCTRINE_SUBSCRIBER = 'doctrine.subscriber';


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'debug' => Expect::bool(false),
			'connection' => Expect::structure([
				'host' => Expect::string()->required(),
				'dbname' => Expect::string()->required(),
				'user' => Expect::string()->required(),
				'password' => Expect::string()->nullable(),
				'url' => Expect::string()->nullable(),
				'pdo' => Expect::string()->nullable(),
				'memory' => Expect::string()->nullable(),
				'driver' => Expect::string('pdo_mysql'),
				'driverClass' => Expect::string()->nullable(),
				'driverOptions' => Expect::array(),
				'unix_socket' => Expect::string()->nullable(),
				'port' => Expect::int()->nullable(),
				'servicename' => Expect::string()->nullable(),
				'charset' => Expect::string('UTF8'),
				'portability' => Expect::int(PortabilityConnection::PORTABILITY_ALL),
				'fetchCase' => Expect::int(\PDO::CASE_LOWER),
				'persistent' => Expect::bool(true),
				'types' => Expect::array(),
				'typesMapping' => Expect::array(),
				'wrapperClass' => Expect::string()->nullable(),
			])->castTo('array')->required(),
			'configuration' => Expect::structure([
				'sqlLogger' => Expect::string()->nullable(),
				'resultCacheImpl' => Expect::string()->nullable(),
				'filterSchemaAssetsExpression' => Expect::string()->nullable(),
				'autoCommit' => Expect::bool()->default(true),
			])->castTo('array'),
			'types' => Expect::arrayOf(Expect::string()),
			'propertyIgnoreAnnotations' => Expect::arrayOf(Expect::string()),
			'deprecatedParameters' => Expect::array(),
		])->castTo('array');
	}


	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$this->loadDoctrineConfiguration();
		$this->loadConnectionConfiguration();
		$this->registerInternalServices();

		if (($this->config['debug'] ?? false) === true) {
			$builder->addDefinition($this->prefix('queryPanel'))
				->setFactory(QueryPanel::class)
				->setAutowired(false);
		}
	}


	public function beforeCompile(): void
	{
		if (\count($this->config['deprecatedParameters'] ?? []) > 0) {
			throw new \RuntimeException(
				'Configuration parameters are deprecated. Please use DI extension instead.' . "\n"
				. 'More information is available here: https://php.baraja.cz/konfigurace-spojeni-s-baraja-doctrine'
			);
		}

		$builder = $this->getContainerBuilder();
		// Dbal
		/** @var ServiceDefinition $eventManager */
		$eventManager = $builder->getDefinition($this->prefix('eventManager'));
		foreach ($builder->findByTag(self::TAG_DOCTRINE_SUBSCRIBER) as $serviceName => $tag) {
			$class = $builder->getDefinition($serviceName)->getType();

			if ($class === null || !is_subclass_of($class, EventSubscriber::class)) {
				throw new \RuntimeException('Subscriber "' . $serviceName . '" does not implement "' . EventSubscriber::class . '".');
			}
			try {
				$eventManager->addSetup('?->addEventListener(?, ?)', [
					'@self',
					call_user_func([(new \ReflectionClass($class))->newInstanceWithoutConstructor(), 'getSubscribedEvents']),
					$serviceName, // Intentionally without @ for laziness.
				]);
			} catch (\ReflectionException $e) {
				throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
			}
		}

		$cache = $this->processCache();

		$types = [];
		foreach ($this->config['types'] ?? [] as $type => $typeClass) {
			if (\class_exists($typeClass) === false) {
				ConfiguratorException::typeDoesNotExist($type, $typeClass);
			}
			$types[$type] = $typeClass;
		}

		/** @var ServiceDefinition $generator */
		$generator = $this->getContainerBuilder()->getDefinitionByType(EntityManager::class);
		$generator->addSetup(
			'?->addInit(function(' . EntityManager::class . ' $entityManager) {' . "\n"
			. "\t" . '// Types' . "\n"
			. "\t" . 'foreach (? as $type => $typeClass) {' . "\n"
			. "\t\t" . 'if (\Doctrine\DBAL\Types\Type::hasType($type) === false) {' . "\n"
			. "\t\t\t" . '\Doctrine\DBAL\Types\Type::addType($type, $typeClass);' . "\n"
			. "\t\t" . '}' . "\n"
			. "\t" . '}' . "\n\n"
			. "\t" . '// Global ignored names' . "\n"
			. "\t" . 'foreach (? as $ignorePropertyAnnotation) {' . "\n"
			. "\t\t" . AnnotationReader::class . '::addGlobalIgnoredName($ignorePropertyAnnotation);' . "\n"
			. "\t" . '}' . "\n\n"
			. "\t" . '$entityManager->setCache(' . $cache['cache'] . ');' . "\n"
			. "\t" . '$entityManager->getConnection()->getSchemaManager()->getDatabasePlatform()'
			. '->registerDoctrineTypeMapping(\'enum\', \'string\');' . "\n"
			. "\t" . '$entityManager->getConfiguration()->addCustomNumericFunction(\'rand\', ' . Rand::class . '::class);' . "\n"
			. "\t" . '$entityManager->buildCache();' . "\n"
			. ($cache['after'] ? "\t" . $cache['after'] . "\n" : '')
			. '})',
			[
				'@self',
				$types,
				$this->config['propertyIgnoreAnnotations'] ?? [],
			]
		);
	}


	/**
	 * Update initialize method
	 */
	public function afterCompile(ClassType $class): void
	{
		if (($this->config['debug'] ?? false) === true) {
			$initialize = $class->getMethod('initialize');
			$initialize->addBody(
				'$this->getService(?)->addPanel($this->getService(?));',
				['tracy.bar', $this->prefix('queryPanel')]
			);
			$initialize->addBody(
				'$this->getService(?)->getConfiguration()->getSqlLogger()->addLogger($this->getService(?));',
				[$this->prefix('connection'), $this->prefix('queryPanel')]
			);
			$initialize->addBody(
				'$this->getService(?)->addPanel(new ?);',
				['tracy.blueScreen', ContainerBuilder::literal(DbalBlueScreen::class)]
			);
		}
	}


	/**
	 * @return string[]
	 */
	private function processCache(): array
	{
		if (Utils::functionIsAvailable('apcu_cache_info')) {
			$cache = new ApcuCache;
			$cache->deleteAll();

			if (Utils::functionIsAvailable('apcu_clear_cache')) {
				@apcu_clear_cache();
			}

			return [
				'cache' => 'new ' . ApcuCache::class,
				'after' => '',
			];
		}

		if (extension_loaded('sqlite3')) {
			return [
				'cache' => 'new ' . SQLite3Cache::class . '('
					. '(function (Baraja\Doctrine\EntityManager $entityManager) {'
					. "\n\t\t" . '$cache = new \SQLite3($entityManager->getDbDirPath());'
					. "\n\t\t" . '$cache->busyTimeout(5000);'
					. "\n\t\t" . '$cache->exec(\'PRAGMA journal_mode = wal;\');'
					. "\n\t\t" . 'return $cache;'
					. "\n\t" . '})($entityManager)'
					. ', \'doctrine\')',
				'after' => '$entityManager->fixDbDirPathPermission();',
			];
		}

		return [
			'cache' => 'null /* CACHE DOES NOT EXIST! */',
			'after' => '',
		];
	}


	/**
	 * Register Doctrine Configuration
	 */
	private function loadDoctrineConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$logger = $builder->addDefinition($this->prefix('logger'))
			->setType(LoggerChain::class)
			->setAutowired('self');

		$configuration = $builder->addDefinition($this->prefix('configuration'));
		$configuration->setFactory(Configuration::class)
			->setAutowired(false)
			->addSetup('setSQLLogger', [$this->prefix('@logger')]);

		if ($this->config['configuration']['sqlLogger'] !== null) { // SqlLogger (append to chain)
			$logger->addSetup('addLogger', [$this->config['configuration']['sqlLogger']]);
		}
		if ($this->config['configuration']['resultCacheImpl'] !== null) { // ResultCacheImpl
			$configuration->addSetup('setResultCacheImpl', [$this->config['configuration']['resultCacheImpl']]);
		}
		if ($this->config['configuration']['filterSchemaAssetsExpression'] !== null) { // FilterSchemaAssetsExpression
			$configuration->addSetup('setFilterSchemaAssetsExpression', [$this->config['configuration']['filterSchemaAssetsExpression']]);
		}

		// AutoCommit
		Validators::assert($this->config['configuration']['autoCommit'], 'bool', 'configuration.autoCommit');
		$configuration->addSetup('setAutoCommit', [$this->config['configuration']['autoCommit']]);
	}


	private function loadConnectionConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		if (preg_match('/^([^:]+):(\d+)$/', $this->config['connection']['host'], $hostParser)) { // parse port from host
			if (isset($this->config['connection']['port']) === true && $this->config['connection']['port'] !== (int) $hostParser[2]) {
				throw new \RuntimeException(
					'Connection port (suffix included in host string) and given port are different.' . "\n"
					. 'Given host "' . $this->config['connection']['host'] . '" contains port "' . $hostParser[2] . '", but given port is "' . $this->config['connection']['port'] . '".' . "\n"
					. 'To solve this issue: Change "host" string to "' . $hostParser[1] . '" (without ":' . $hostParser[2] . '") or change port to "' . $this->config['connection']['port'] . '".'
				);
			}
			$this->config['connection']['host'] = $hostParser[1];
			$this->config['connection']['port'] = (int) $hostParser[2];
		}
		if (isset($this->config['connection']['port']) === false && preg_match('/^.+?\.ondigitalocean\.com$/', $this->config['connection']['host'])) { // DigitalOcean managed database support
			throw new \RuntimeException(
				'In case of DigitalOcean (host is "' . $this->config['connection']['host'] . '") you must define port (as integer) in your NEON configuration, but NULL given.' . "\n"
				. 'Hint: Check if your current IP "' . Utils::userIp() . '" is allowed for connection.'
			);
		}

		$builder->addDefinition($this->prefix('eventManager'))
			->setFactory(ContainerAwareEventManager::class);

		if (($this->config['debug'] ?? false) === true) {
			$builder->getDefinition($this->prefix('eventManager'))
				->setAutowired(false);
			$builder->addDefinition($this->prefix('eventManager.debug'))
				->setFactory(DebugEventManager::class, [$this->prefix('@eventManager')]);
		}

		$builder->addDefinition($this->prefix('connectionFactory'))
			->setFactory(ConnectionFactory::class, [$this->config['connection']['types'], $this->config['connection']['typesMapping']]);

		$builder->addDefinition($this->prefix('connection'))
			->setFactory(Connection::class)
			->setFactory('@' . $this->prefix('connectionFactory') . '::createConnection', [
				$this->config['connection'],
				'@' . $this->prefix('configuration'),
				$builder->getDefinitionByType(EventManager::class),
			]);
	}


	private function registerInternalServices(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('entityManagerDependencies'))
			->setFactory(EntityManagerDependencies::class);

		$builder->addAccessorDefinition($this->prefix('entityManagerDependenciesAccessor'))
			->setImplement(EntityManagerDependenciesAccessor::class);

		$builder->addDefinition($this->prefix('doctrineHelper'))
			->setFactory(DoctrineHelper::class);

		$builder->addAccessorDefinition($this->prefix('doctrineHelperAccessor'))
			->setImplement(DoctrineHelperAccessor::class);
	}
}
