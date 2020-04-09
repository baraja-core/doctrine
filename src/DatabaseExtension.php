<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\SQLite3Cache;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

final class DatabaseExtension extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'types' => Expect::arrayOf(Expect::string()),
			'propertyIgnoreAnnotations' => Expect::arrayOf(Expect::string()),
		])->castTo('array');
	}


	public function beforeCompile(): void
	{
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
