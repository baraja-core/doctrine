<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\PackageManager\Console;
use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\SQLite3Cache;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Helpers;

class DatabaseExtension extends CompilerExtension
{

	/**
	 * @var string[]
	 */
	private $types = [];

	/**
	 * @param ClassType $class
	 */
	public function afterCompile(ClassType $class): void
	{
		$this->types = $this->getConfig();
		$initialize = $class->getMethod('initialize');

		$initialize->setBody(
			EntityManager::class . '::addInit(function(' . EntityManager::class . ' $entityManager) {' . "\n"
			. $this->getTypeDefinition() . "\n"
			. "\t" . '$entityManager->setCache(' . $this->processCache() . ');' . "\n"
			. "\t" . '$entityManager->getConnection()->getSchemaManager()->getDatabasePlatform()'
			. '->registerDoctrineTypeMapping(\'enum\', \'string\');' . "\n"
			. "\t" . '$entityManager->getConfiguration()->addCustomNumericFunction(\'rand\', ' . Rand::class . '::class);' . "\n"
			. "\t" . '$entityManager->buildCache();' . "\n"
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
			$return .= "\t" . '\Doctrine\DBAL\Types\Type::addType('
				. Helpers::dump($name) . ',' . Helpers::dump($className)
				. ');' . "\n";
		}

		return $return;
	}

	/**
	 * @return string
	 */
	private function processCache(): string
	{
		if (Utils::functionIsAvailable('apcu_cache_info')) {
			$cache = new ApcuCache;
			$cache->deleteAll();

			if (Utils::functionIsAvailable('apcu_clear_cache')) {
				@apcu_clear_cache();
			}

			return 'new ' . ApcuCache::class;
		}

		if (extension_loaded('sqlite3')) {
			return 'new ' . SQLite3Cache::class . '('
				. '(function (Baraja\Doctrine\EntityManager $entityManager) {'
				. "\n\t" . '$cache = new \SQLite3($entityManager->getDbDirPath());'
				. "\n\t" . '$cache->busyTimeout(5000);'
				. "\n\t" . '$cache->exec(\'PRAGMA journal_mode = wal;\');'
				. "\n\t" . 'return $cache;'
				. "\n\t" . '})($entityManager)'
				. ', \'doctrine\')';
		}

		return 'null /* CACHE DOES NOT EXIST! */';
	}

}
