<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\PackageManager\Console;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\SQLite3Cache;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Helpers;

final class DatabaseExtension extends CompilerExtension
{

	/** @var string[] */
	private $types = [];


	/**
	 * @param ClassType $class
	 */
	public function afterCompile(ClassType $class): void
	{
		$this->types = $this->getConfig();
		$initialize = $class->getMethod('initialize');
		$cache = $this->processCache();

		$initialize->setBody(
			EntityManager::class . '::addInit(function(' . EntityManager::class . ' $entityManager) {' . "\n"
			. $this->getTypeDefinition() . "\n"
			. "\t" . '$entityManager->setCache(' . $cache['cache'] . ');' . "\n"
			. "\t" . '$entityManager->getConnection()->getSchemaManager()->getDatabasePlatform()'
			. '->registerDoctrineTypeMapping(\'enum\', \'string\');' . "\n"
			. "\t" . '$entityManager->getConfiguration()->addCustomNumericFunction(\'rand\', ' . Rand::class . '::class);' . "\n"
			. "\t" . '$entityManager->buildCache();' . "\n"
			. ($cache['after'] ? "\t" . $cache['after'] . "\n" : '')
			. '});' . "\n"
			. $initialize->getBody()
			. (PHP_SAPI === 'cli' && class_exists(Console::class) === false ? "\n"
				. OrmSchemaUpdateTool::class . '::setContainer($this);' . "\n"
				. 'register_shutdown_function([' . OrmSchemaUpdateTool::class . '::class, \'run\']);' : '')
		);
	}


	/**
	 * @return string
	 */
	private function getTypeDefinition(): string
	{
		$return = '';

		foreach ($this->getConfig()['types'] ?? [] as $name => $className) {
			if (\class_exists($className) === false) {
				ConfiguratorException::typeDoesNotExist($name, $className);
			}
			$return .= "\t" . 'if (\Doctrine\DBAL\Types\Type::hasType(' . Helpers::dump($name) . ') === false) { '
				. '\Doctrine\DBAL\Types\Type::addType('
				. Helpers::dump($name) . ',' . Helpers::dump($className)
				. '); }' . "\n";
		}

		foreach ($this->getConfig()['propertyIgnoreAnnotations'] ?? [] as $ignorePropertyAnnotation) {
			$return .= "\t" . AnnotationReader::class . '::addGlobalIgnoredName(\'' . $ignorePropertyAnnotation . '\');' . "\n";
		}

		return $return;
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
}
