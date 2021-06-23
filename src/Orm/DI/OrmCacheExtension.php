<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM\DI;


use Baraja\Doctrine\Cache\ApcuCache;
use Baraja\Doctrine\Cache\ArrayCache;
use Baraja\Doctrine\Cache\FilesystemCache;
use Doctrine\ORM\Cache\CacheConfiguration;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\Configuration;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\InvalidStateException;

final class OrmCacheExtension extends CompilerExtension
{
	public const DRIVERS = [
		'apcu' => ApcuCache::class,
		'array' => ArrayCache::class,
		'filesystem' => FilesystemCache::class,
	];

	/** @var mixed[] */
	private array $defaults = [
		'defaultDriver' => 'filesystem',
		'queryCache' => null,
		'hydrationCache' => null,
		'metadataCache' => null,
		'resultCache' => null,
		'secondLevelCache' => null,
	];


	public function loadConfiguration(): void
	{
		if ($this->compiler->getExtensions(OrmExtension::class) === []) {
			throw new \RuntimeException('You should register "' . self::class . '" before "' . static::class . '".');
		}

		$this->validateConfig($this->defaults);
		$this->loadQueryCacheConfiguration();
		$this->loadHydrationCacheConfiguration();
		$this->loadResultCacheConfiguration();
		$this->loadMetadataCacheConfiguration();
		$this->loadSecondLevelCacheConfiguration();
	}


	public function loadQueryCacheConfiguration(): void
	{
		/** @var mixed[] $config */
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();
		/** @var \Nette\DI\Definitions\ServiceDefinition $configuration */
		$configuration = $builder->getDefinitionByType(Configuration::class);

		if ($config['queryCache'] === null && $config['defaultDriver'] !== null) {
			$configuration->addSetup('setQueryCacheImpl', [$this->getDefaultDriverCache('queryCache')]);
		} elseif ($config['queryCache'] !== null) {
			$builder->addDefinition($this->prefix('queryCache'))
				->setFactory($config['queryCache']);
			$configuration->addSetup('setQueryCacheImpl', [$this->prefix('@queryCache')]);
		} else {
			throw new InvalidStateException('QueryCache or defaultDriver must be provided');
		}
	}


	public function loadResultCacheConfiguration(): void
	{
		/** @var mixed[] $config */
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();
		/** @var \Nette\DI\Definitions\ServiceDefinition $configuration */
		$configuration = $builder->getDefinitionByType(Configuration::class);

		if ($config['resultCache'] === null && $config['defaultDriver'] !== null) {
			$configuration->addSetup('setResultCacheImpl', [$this->getDefaultDriverCache('resultCache')]);
		} elseif ($config['resultCache'] !== null) {
			$builder->addDefinition($this->prefix('resultCache'))
				->setFactory($config['resultCache']);
			$configuration->addSetup('setResultCacheImpl', [$this->prefix('@hydrationCache')]);
		} else {
			throw new InvalidStateException('ResultCache or defaultDriver must be provided');
		}
	}


	public function loadHydrationCacheConfiguration(): void
	{
		/** @var mixed[] $config */
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();
		/** @var \Nette\DI\Definitions\ServiceDefinition $configuration */
		$configuration = $builder->getDefinitionByType(Configuration::class);

		if ($config['hydrationCache'] === null && $config['defaultDriver'] !== null) {
			$configuration->addSetup('setHydrationCacheImpl', [$this->getDefaultDriverCache('hydrationCache')]);
		} elseif ($config['hydrationCache'] !== null) {
			$builder->addDefinition($this->prefix('hydrationCache'))
				->setFactory($config['hydrationCache']);
			$configuration->addSetup('setHydrationCacheImpl', [$this->prefix('@hydrationCache')]);
		} else {
			throw new InvalidStateException('HydrationCache or defaultDriver must be provided');
		}
	}


	public function loadMetadataCacheConfiguration(): void
	{
		/** @var mixed[] $config */
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();
		/** @var \Nette\DI\Definitions\ServiceDefinition $configuration */
		$configuration = $builder->getDefinitionByType(Configuration::class);

		if ($config['metadataCache'] === null && $config['defaultDriver'] !== null) {
			$configuration->addSetup('setMetadataCacheImpl', [$this->getDefaultDriverCache('metadataCache')]);
		} elseif ($config['metadataCache'] !== null) {
			$builder->addDefinition($this->prefix('metadataCache'))
				->setFactory($config['metadataCache']);
			$configuration->addSetup('setMetadataCacheImpl', [$this->prefix('@metadataCache')]);
		} else {
			throw new InvalidStateException('MetadataCache or defaultDriver must be provided');
		}
	}


	public function loadSecondLevelCacheConfiguration(): void
	{
		/** @var mixed[] $config */
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();
		/** @var \Nette\DI\Definitions\ServiceDefinition $configuration */
		$configuration = $builder->getDefinitionByType(Configuration::class);

		if ($config['secondLevelCache'] === null && $config['defaultDriver'] !== null) {
			$regions = $builder->addDefinition($this->prefix('regions'))
				->setFactory(RegionsConfiguration::class)
				->setAutowired(false);
			$cacheFactory = $builder->addDefinition($this->prefix('cacheFactory'))
				->setFactory(DefaultCacheFactory::class)
				->setArguments([$regions, $this->getDefaultDriverCache('secondLevelCache')])
				->setAutowired(false);
			$cacheConfiguration = $builder->addDefinition($this->prefix('cacheConfiguration'))
				->setFactory(CacheConfiguration::class)
				->addSetup('setCacheFactory', [$cacheFactory])
				->setAutowired(false);
			$configuration->addSetup('setSecondLevelCacheEnabled', [true]);
			$configuration->addSetup('setSecondLevelCacheConfiguration', [$cacheConfiguration]);
		} elseif ($config['secondLevelCache'] !== null) {
			$configuration->addSetup('setSecondLevelCacheEnabled', [true]);
			$configuration->addSetup('setSecondLevelCacheConfiguration', [$config['secondLevelCache']]);
		}
	}


	private function getDefaultDriverCache(string $service): ServiceDefinition
	{
		/** @var mixed[] $config */
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();

		if (!isset(self::DRIVERS[$config['defaultDriver']])) {
			throw new InvalidStateException(sprintf('Unsupported default driver "%s"', $config['defaultDriver']));
		}

		$driverCache = $builder->addDefinition($this->prefix($service))
			->setFactory(self::DRIVERS[$config['defaultDriver']])
			->setAutowired(false);

		if (($config['defaultDriver'] ?? '') === 'filesystem') {
			$driverCache->setArguments([$builder->parameters['tempDir'] . '/cache/Doctrine.Cache.' . ucfirst($service)]);
		}

		return $driverCache;
	}
}
