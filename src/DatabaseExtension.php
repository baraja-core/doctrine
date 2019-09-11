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
			$this->getTypeDefinition() . "\n"
			. '/** @var ' . EntityManager::class . ' $entityManager */' . "\n"
			. '$entityManager = $this->getByType(' . EntityManager::class . '::class);' . "\n"
			. '$entityManager->setCache(' . $this->processCache() . ');' . "\n"
			. '$entityManager->getConnection()->getSchemaManager()->getDatabasePlatform()'
			. '->registerDoctrineTypeMapping(\'enum\', \'string\');' . "\n"
			. '$entityManager->getConfiguration()->addCustomNumericFunction(\'rand\', ' . Rand::class . '::class);' . "\n"
			. '$entityManager->buildCache();' . "\n"
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
			$return .= '\Doctrine\DBAL\Types\Type::addType('
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
			return 'new ' . SQLite3Cache::class . '(new \SQLite3($entityManager->getDbDirPath() . \'/doctrine.db\'), \'doctrine\')';
		}

		return 'null /* CACHE DOES NOT EXIST! */';
	}

}
