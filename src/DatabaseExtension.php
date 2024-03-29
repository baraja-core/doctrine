<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\Doctrine\Bridge\DoctrineEntityRepository;
use Baraja\Doctrine\Cache\ApcuCache;
use Baraja\Doctrine\Cache\SQLite3Cache;
use Baraja\Doctrine\DBAL\ConnectionFactory;
use Baraja\Doctrine\DBAL\Events\ContainerAwareEventManager;
use Baraja\Doctrine\DBAL\Events\DebugEventManager;
use Baraja\Doctrine\DBAL\Tracy\BlueScreen\DbalBlueScreen;
use Baraja\Doctrine\DBAL\Tracy\QueryPanel\QueryPanel;
use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Baraja\Doctrine\UUID\UuidBinaryType;
use Baraja\Doctrine\UUID\UuidType;
use Baraja\Network\Ip;
use Baraja\ServiceMethodInvoker\ProjectEntityRepository;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\Portability\Connection as PortabilityConnection;
use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;
use Doctrine\DBAL\Types\Type;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Validators;
use Tracy\Debugger;

/**
 * @method array{
 *     connection: array{
 *        host: string|null,
 *        dbname: string|null,
 *        user: string|null,
 *        password: string|null,
 *        url: string|null,
 *        pdo: string|null,
 *        memory: string|null,
 *        driver: non-empty-string,
 *        driverClass: class-string|null,
 *        driverOptions: array<string, mixed>,
 *        unix_socket: string|null,
 *        port: int|null,
 *        servicename: string|null,
 *        charset: non-empty-string,
 *        portability: int,
 *        fetchCase: int,
 *        persistent: bool,
 *        types: array<string, array{class: class-string<Type>, commented: string|null}>,
 *        typesMapping: array<string, class-string>,
 *        wrapperClass: string|null,
 *        serverVersion: string|null,
 *     },
 *     configuration: array{
 *        sqlLogger: string|null,
 *        resultCacheImpl: string|null,
 *        filterSchemaAssetsExpression: string|null,
 *        autoCommit: bool,
 *     },
 *     cache?: string,
 *     types: array<string, class-string>,
 *     customNumericFunctions: array<string, class-string>,
 *     propertyIgnoreAnnotations: array<int, string>
 * } getConfig()
 */
final class DatabaseExtension extends CompilerExtension
{
	public const TAG_DOCTRINE_SUBSCRIBER = 'doctrine.subscriber';

	/** @var array<string, class-string> (type => typeClass) */
	private static array $customTypes = [
		'uuid' => UuidType::class,
		'uuid-binary' => UuidBinaryType::class,
	];

	/** @var array<string, class-string> (name => typeClass) */
	private static array $customNumericFunctions = [
		'RAND' => RandFunction::class,
		'ROUND' => RoundFunction::class,
		'GEODISTANCE' => GeoDistanceFunction::class,
		'MATCH' => MatchAgainstFunction::class,
	];


	/**
	 * @return string[]
	 */
	public static function mustBeDefinedAfter(): array
	{
		return [OrmAnnotationsExtension::class];
	}


	public static function addCustomType(string $type, string $typeClass): void
	{
		if ((self::$customTypes[$type] ?? $typeClass) !== $typeClass) {
			throw new \InvalidArgumentException(sprintf(
				'Database type "%s" already exist. Previous type "%s", but type class "%s" given.',
				$type,
				self::$customTypes[$type],
				$typeClass,
			));
		}
		if (!class_exists($typeClass)) {
			throw new \InvalidArgumentException(sprintf('Type "%s" is not valid class.', $typeClass));
		}
		self::$customTypes[$type] = $typeClass;
	}


	public static function addCustomNumericFunction(string $name, string $type): void
	{
		if (!class_exists($type)) {
			throw new \InvalidArgumentException(sprintf('Type "%s" is not valid class.', $type));
		}
		self::$customNumericFunctions[$name] = $type;
	}


	public function getConfigSchema(): Schema
	{
		return Expect::structure(
			[
				'connection' => Expect::structure(
					[
						'host' => Expect::string()->nullable(),
						'dbname' => Expect::string()->nullable(),
						'user' => Expect::string()->nullable(),
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
						'serverVersion' => Expect::string()->nullable(),
					],
				)->castTo('array')->required(),
				'configuration' => Expect::structure(
					[
						'sqlLogger' => Expect::string()->nullable(),
						'resultCacheImpl' => Expect::string()->nullable(),
						'filterSchemaAssetsExpression' => Expect::string()->nullable(),
						'autoCommit' => Expect::bool()->default(true),
					],
				)->castTo('array'),
				'cache' => Expect::string(),
				'types' => Expect::arrayOf(Expect::string())->default([]),
				'customNumericFunctions' => Expect::arrayOf(Expect::string())->default([]),
				'propertyIgnoreAnnotations' => Expect::arrayOf(Expect::string())->default([]),
			],
		)->castTo('array')->otherItems(Expect::mixed());
	}


	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		OrmAnnotationsExtension::addAnnotationPathToManager(
			$builder,
			'Baraja\Doctrine\Entity',
			__DIR__ . '/Entity',
		);

		$this->loadDoctrineConfiguration();
		$this->loadConnectionConfiguration();
		$this->registerInternalServices();
	}


	public function beforeCompile(): void
	{
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();

		// Dbal
		/** @var ServiceDefinition $eventManager */
		$eventManager = $builder->getDefinition($this->prefix('eventManager'));
		foreach (array_keys($builder->findByTag(self::TAG_DOCTRINE_SUBSCRIBER)) as $serviceName) {
			$class = $builder->getDefinition($serviceName)->getType();
			if ($class === null || !is_subclass_of($class, EventSubscriber::class)) {
				throw new \RuntimeException(
					'Subscriber "' . $serviceName . '" does not implement "' . EventSubscriber::class . '".',
				);
			}
			try {
				$eventManager->addSetup(
					'?->addEventListener(?, ?)',
					[
						'@self',
						call_user_func(
							[
								(new \ReflectionClass($class))->newInstanceWithoutConstructor(),
								'getSubscribedEvents',
							],
						),
						$serviceName, // Intentionally without @ for laziness.
					],
				);
			} catch (\ReflectionException $e) {
				throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
			}
		}

		$types = [];
		foreach (array_merge(self::$customTypes, $config['types']) as $type => $typeClass) {
			if (\class_exists($typeClass) === false) {
				throw new ConfiguratorException(sprintf('Doctrine type "%s" does not exist, because class "%s" is not defined.', $type, $typeClass));
			}
			$types[$type] = $typeClass;
		}

		$functionsCode = '';
		foreach (
			array_merge(
				self::$customNumericFunctions,
				$config['customNumericFunctions'],
			) as $functionName => $functionType
		) {
			if (class_exists($functionType) === false) {
				throw new ConfiguratorException(sprintf('Doctrine function definition "%s" does not exist, because class "%s" is not defined.', $functionName, $functionType));
			}
			$functionsCode .= ($functionsCode !== '' ? "\n\t" : '')
				. '$entityManager->getConfiguration()->addCustomNumericFunction(\'' . strtoupper($functionName) . '\', '
				. $functionType . '::class);';
		}

		if (interface_exists(ProjectEntityRepository::class)) {
			$builder->addDefinition($this->prefix('projectEntityRepository'))
				->setFactory(DoctrineEntityRepository::class);
		}

		/** @var ServiceDefinition $generator */
		$generator = $this->getContainerBuilder()->getDefinitionByType(EntityManager::class);
		$generator->addSetup(
			'?->addInit(static function(' . EntityManager::class . ' $entityManager): void {' . "\n"
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
			. "\t" . '$entityManager->setCache(' . $this->processCache($config['cache'] ?? null) . ');' . "\n"
			. "\t" . '$entityManager->getConnection()->getSchemaManager()->getDatabasePlatform()'
			. '->registerDoctrineTypeMapping(\'enum\', \'string\');' . "\n"
			. "\t" . $functionsCode . "\n"
			. "\t" . '$entityManager->buildCache();' . "\n"
			. '})',
			[
				'@self',
				$types,
				array_merge(
					[
						'sample',
						'endpointName',
						'editable',
					],
					$config['propertyIgnoreAnnotations'],
				),
			],
		);
	}


	/**
	 * Update initialize method
	 */
	public function afterCompile(ClassType $class): void
	{
		if (($this->getContainerBuilder()->parameters['debugMode'] ?? false) === true) {
			$initialize = $class->getMethod('initialize');
			$initialize->addBody(
				'$this->getService(?)->addPanel(new ?);',
				['tracy.blueScreen', ContainerBuilder::literal(DbalBlueScreen::class)],
			);
			if (\class_exists(Debugger::class) === true) {
				/** @var ServiceDefinition $entityManager */
				$entityManager = $this->getContainerBuilder()->getDefinitionByType(EntityManager::class);
				$entityManager->setArgument('panel', '@' . QueryPanel::class);
			}
		}
	}


	private function processCache(?string $cache = null): string
	{
		if ($cache !== null) {
			if (\class_exists($cache) === true) {
				return 'new ' . $cache;
			}
			if (\in_array($cache, ['apcu', 'sqlite'], true) === false) {
				throw new \RuntimeException('Cache service "' . $cache . '" does not exist.');
			}
		}
		if (($cache === null || $cache === 'apcu') && Utils::functionIsAvailable('apcu_cache_info')) {
			$apcuCache = new ApcuCache;
			$apcuCache->deleteAll();
			if (Utils::functionIsAvailable('apcu_clear_cache')) {
				@apcu_clear_cache();
			}

			return 'new ' . ApcuCache::class;
		}
		if (($cache === null || $cache === 'sqlite') && extension_loaded('sqlite3')) {
			/** @var ServiceDefinition $entityManager */
			$entityManager = $this->getContainerBuilder()->getDefinitionByType(EntityManager::class);
			$entityManager->addSetup('?->fixDbDirPathPermission()', ['@self']);

			return 'new ' . SQLite3Cache::class . '($entityManager->getDbDirPath())';
		}

		return 'null /* CACHE DOES NOT EXIST! */';
	}


	/**
	 * Register Doctrine Configuration
	 */
	private function loadDoctrineConfiguration(): void
	{
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();

		$logger = $builder->addDefinition($this->prefix('logger'))
			->setType(LoggerChain::class)
			->setAutowired('self');

		$configuration = $builder->addDefinition($this->prefix('configuration'));
		$configuration->setFactory(Configuration::class)
			->setAutowired(false)
			->addSetup('setSQLLogger', [$this->prefix('@logger')]);

		$loggers = [];
		if ($config['configuration']['sqlLogger'] !== null) { // SqlLogger (append to chain)
			$loggers[] = '@' . $config['configuration']['sqlLogger'];
		}
		if ($builder->hasDefinition('doctrine.queryPanel')) {
			$loggers[] = $builder->getDefinition('doctrine.queryPanel');
		}
		$logger->setArgument('loggers', $loggers);

		if ($config['configuration']['resultCacheImpl'] !== null) { // ResultCacheImpl
			$configuration->addSetup('setResultCacheImpl', [$config['configuration']['resultCacheImpl']]);
		}
		if ($config['configuration']['filterSchemaAssetsExpression'] !== null) { // FilterSchemaAssetsExpression
			$configuration->addSetup(
				'setFilterSchemaAssetsExpression',
				[$config['configuration']['filterSchemaAssetsExpression']],
			);
		}

		// AutoCommit
		Validators::assert($config['configuration']['autoCommit'], 'bool', 'configuration.autoCommit');
		$configuration->addSetup('setAutoCommit', [$config['configuration']['autoCommit']]);
	}


	private function loadConnectionConfiguration(): void
	{
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();

		$connection = $config['connection'];
		if (isset($connection['host'], $connection['dbname'], $connection['user'])) {
			$host = $connection['host'];
			if (preg_match('/^([^:]+):(\d+)$/', $host, $hostParser) === 1) { // parse port from host
				if (
					isset($config['connection']['port']) === true
					&& $config['connection']['port'] !== (int) $hostParser[2]
				) {
					throw new \RuntimeException(
						'Connection port (suffix included in host string) and given port are different.' . "\n"
						. 'Given host "' . $config['connection']['host'] . '" contains port "' . $hostParser[2] . '", '
						. 'but given port is "' . $config['connection']['port'] . '".' . "\n"
						. 'To solve this issue: Change "host" string to "' . $hostParser[1] . '" '
						. '(without ":' . $hostParser[2] . '") or change port to "' . $config['connection']['port'] . '".',
					);
				}
				$config['connection']['host'] = $hostParser[1];
				$config['connection']['port'] = (int) $hostParser[2];
			}
			if (
				isset($config['connection']['port']) === false
				&& preg_match('/^.+?\.ondigitalocean\.com$/', $host) === 1
			) { // DigitalOcean managed database support
				throw new \RuntimeException(
					'In case of DigitalOcean (host is "' . $host . '") '
					. 'you must define port (as integer) in your NEON configuration, but NULL given.'
					. "\n" . 'Hint: Check if your current IP "' . Ip::get() . '" is allowed for connection.',
				);
			}
		} elseif ($connection['url'] !== null) {
			if (preg_match('/^[a-z-]+:\/{2,}/', $connection['url']) !== 1) {
				throw new \RuntimeException(
					'Connection URL is invalid. '
					. 'Please read configuration notes: https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html',
				);
			}
		} elseif (is_string(getenv('DB_URI'))) { // try use environment variable
			$connectionString = trim(getenv('DB_URI') ?: '');
			if ($connectionString === '') {
				throw new \RuntimeException(
					'Connection configuration is invalid. '
					. 'Connection string (key "DB_URI") is not valid string.',
				);
			}
			if (preg_match('~^[a-z]+://~', $connectionString) !== 1) { // fix missing driver
				$connectionString = 'mysql://' . $connectionString;
			}
			$dbName = getenv('DB_NAME');
			if ($dbName !== false && $dbName !== '') {
				$config['connection']['dbname'] = $dbName;
			}
			if (
				preg_match(
					'/^([a-z]+):\/\/(?<user>[^:]+):(?<password>[^:@]+)@(?<host>[^:]+):(?<port>\d+)\/(?<dbname>[a-z]+)$/',
					$connectionString,
					$connectionStringParser,
				) === 1
			) {
				$config['connection']['user'] = $connectionStringParser['user'];
				$config['connection']['password'] = $connectionStringParser['password'];
				$config['connection']['host'] = $connectionStringParser['host'];
				$config['connection']['port'] = (int) $connectionStringParser['port'];
				$config['connection']['dbname'] = $connectionStringParser['dbname'];
			}
			$config['connection']['url'] = $connectionString;
		} else {
			throw new \RuntimeException(
				'Connection configuration is invalid. '
				. 'The mandatory items ("host", "dbname", "username") or "url" is missing.',
			);
		}

		$builder->addDefinition($this->prefix('eventManager'))
			->setFactory(ContainerAwareEventManager::class);

		if (($builder->parameters['debugMode'] ?? false) === true) {
			$builder->getDefinition($this->prefix('eventManager'))
				->setAutowired(false);
			$builder->addDefinition($this->prefix('eventManager.debug'))
				->setFactory(DebugEventManager::class, [$this->prefix('@eventManager')]);
		}

		$builder->addDefinition($this->prefix('connectionFactory'))
			->setFactory(
				ConnectionFactory::class,
				[$config['connection']['types'], $config['connection']['typesMapping']],
			);

		$builder->addDefinition($this->prefix('connection'))
			->setFactory(Connection::class)
			->setFactory(
				'@' . $this->prefix('connectionFactory') . '::createConnection',
				[
					$config['connection'],
					'@' . $this->prefix('configuration'),
					$builder->getDefinitionByType(EventManager::class),
				],
			);

		$builder->addDefinition($this->prefix('singleConnectionProvider'))
			->setFactory(SingleConnectionProvider::class);
	}


	private function registerInternalServices(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('doctrineHelper'))
			->setFactory(DoctrineHelper::class);

		$builder->addAccessorDefinition($this->prefix('doctrineHelperAccessor'))
			->setImplement(DoctrineHelperAccessor::class);
	}
}
