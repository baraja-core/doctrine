<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM\DI;


use Baraja\Doctrine\ORM\Exception\Logical\InvalidStateException;
use Baraja\Doctrine\ORM\Mapping\AnnotationDriver;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Cache\ApcCache;
use Doctrine\Common\Cache\ApcuCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\MemcacheCache;
use Doctrine\Common\Cache\MemcachedCache;
use Doctrine\Common\Cache\RedisCache;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\Common\Cache\XcacheCache;
use Doctrine\ORM\Configuration;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Helpers;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Validators;

final class OrmAnnotationsExtension extends CompilerExtension
{
	public const DRIVERS = [
		'apc' => ApcCache::class,
		'apcu' => ApcuCache::class,
		'array' => ArrayCache::class,
		'filesystem' => FilesystemCache::class,
		'memcache' => MemcacheCache::class,
		'memcached' => MemcachedCache::class,
		'redis' => RedisCache::class,
		'void' => VoidCache::class,
		'xcache' => XcacheCache::class,
	];

	/** @var string[] */
	private static array $annotationPaths = [];


	/**
	 * @return string[]
	 */
	public static function mustBeDefinedAfter(): array
	{
		return [OrmConsoleExtension::class, OrmExtension::class];
	}


	public static function addAnnotationPath(string $namespace, string $directoryPath): void
	{
		if (\is_dir($directoryPath) === false) {
			throw new \RuntimeException('Path "' . $directoryPath . '" is not valid directory.');
		}
		if (isset(self::$annotationPaths[$namespace]) === true) {
			throw new \RuntimeException('Definition for namespace "' . $namespace . '" already exist.');
		}

		self::$annotationPaths[$namespace] = $directoryPath;
	}


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'paths' => Expect::arrayOf(Expect::string()),
			'excludePaths' => Expect::arrayOf(Expect::string()),
			'ignore' => Expect::arrayOf(Expect::string()),
			'defaultCache' => Expect::string('filesystem'),
			'cache' => Expect::string(),
			'debug' => Expect::bool(false),
		])->castTo('array');
	}


	public function loadConfiguration(): void
	{
		if ($this->compiler->getExtensions(OrmExtension::class) === []) {
			throw new \RuntimeException(__CLASS__ . ': Extension "' . OrmExtension::class . '" must be defined before this extension.');
		}

		/** @var mixed[] $config */
		$config = $this->config;
		$builder = $this->getContainerBuilder();

		$reader = $builder->addDefinition($this->prefix('annotationReader'))
			->setType(AnnotationReader::class)
			->setAutowired(false);

		Validators::assertField($config, 'ignore', 'array');

		foreach ($config['ignore'] as $annotationName) {
			$reader->addSetup('addGlobalIgnoredName', [$annotationName]);
			AnnotationReader::addGlobalIgnoredName($annotationName);
		}

		if ($config['cache'] === null && $config['defaultCache'] !== null) {
			$this->getDefaultCache()
				->setAutowired(false);
		} elseif ($config['cache'] !== null) {
			$builder->addDefinition($this->prefix('annotationsCache'))
				->setFactory($config['cache'])
				->setAutowired(false);
		} else {
			throw new InvalidStateException('Cache or defaultCache must be provided');
		}

		$builder->addDefinition($this->prefix('reader'))
			->setType(Reader::class)
			->setFactory(CachedReader::class, [
				$this->prefix('@annotationReader'),
				$this->prefix('@annotationsCache'),
				$config['debug'],
			]);

		AnnotationRegistry::registerUniqueLoader('class_exists');
	}


	public function beforeCompile(): void
	{
		/** @var mixed[] $config */
		$config = $this->config;
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('annotationDriver'))
			->setFactory(AnnotationDriver::class, [$this->prefix('@reader'), Helpers::expand(array_merge(self::$annotationPaths, $config['paths']), $builder->parameters)])
			->addSetup('addExcludePaths', [Helpers::expand($config['excludePaths'], $builder->parameters)]);

		/** @var ServiceDefinition $configurationDefinition */
		$configurationDefinition = $builder->getDefinitionByType(Configuration::class);
		$configurationDefinition->addSetup('setMetadataDriverImpl', [$this->prefix('@annotationDriver')]);
	}


	public function afterCompile(ClassType $classType): void
	{
		$initialize = $classType->getMethod('initialize');
		$original = (string) $initialize->getBody();
		$initialize->setBody('?::registerUniqueLoader(\'class_exists\');' . "\n", [new PhpLiteral(AnnotationRegistry::class)]);
		$initialize->addBody($original);
	}


	private function getDefaultCache(): ServiceDefinition
	{
		/** @var mixed[] $config */
		$config = $this->config;
		$builder = $this->getContainerBuilder();

		if (!isset(self::DRIVERS[$config['defaultCache']])) {
			throw new InvalidStateException(sprintf('Unsupported default cache driver "%s"', $config['defaultCache']));
		}

		$driverCache = $builder->addDefinition($this->prefix('annotationsCache'))
			->setFactory(self::DRIVERS[$config['defaultCache']])
			->setAutowired(false);

		if ($config['defaultCache'] === 'filesystem') {
			$driverCache->setArguments([$builder->parameters['tempDir'] . '/cache/Doctrine.Annotations']);
		}

		return $driverCache;
	}
}
