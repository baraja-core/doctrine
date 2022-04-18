<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM\DI;


use Baraja\Doctrine\Cache\ApcuCache;
use Baraja\Doctrine\Cache\ArrayCache;
use Baraja\Doctrine\Cache\FilesystemCache;
use Baraja\Doctrine\ORM\Exception\Logical\InvalidStateException;
use Baraja\Doctrine\ORM\Mapping\AnnotationDriver;
use Baraja\Doctrine\ORM\Mapping\EntityAnnotationManager;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\Configuration;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Helpers;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\FileSystem;
use Nette\Utils\Validators;

/**
 * @method array{
 *    paths: array<string, string>,
 *    excludePaths: array<int, string>,
 *    ignore: array<int, string>,
 *    defaultCache: string,
 *    cache: string|null,
 *    debug: bool
 * } getConfig()
 */
final class OrmAnnotationsExtension extends CompilerExtension
{
	public const Drivers = [
		'apcu' => ApcuCache::class,
		'array' => ArrayCache::class,
		'filesystem' => FilesystemCache::class,
	];


	/**
	 * @return string[]
	 */
	public static function mustBeDefinedAfter(): array
	{
		return [OrmConsoleExtension::class, OrmExtension::class];
	}


	public static function addAnnotationPathToManager(
		ContainerBuilder $builder,
		string $namespace,
		string $directoryPath,
	): void {
		self::createEntityAnnotationManager($builder)
			->addSetup('?->addPath(?, ?)', ['@self', $namespace, $directoryPath]);
	}


	private static function createEntityAnnotationManager(ContainerBuilder $builder): ServiceDefinition
	{
		static $exist = false;
		if ($exist === false) {
			$manager = $builder->addDefinition('barajaDoctrine.entityAnnotationManager')
				->setFactory(EntityAnnotationManager::class);
			$exist = true;
		} else {
			$manager = $builder->getDefinitionByType(EntityAnnotationManager::class);
		}

		/** @var ServiceDefinition $manager */
		return $manager;
	}


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'paths' => Expect::arrayOf(Expect::string())->default([]),
			'excludePaths' => Expect::arrayOf(Expect::string())->default([]),
			'ignore' => Expect::arrayOf(Expect::string())->default([]),
			'defaultCache' => Expect::string('filesystem'),
			'cache' => Expect::string(),
			'debug' => Expect::bool(false),
		])->castTo('array');
	}


	public function loadConfiguration(): void
	{
		if ($this->compiler->getExtensions(OrmExtension::class) === []) {
			throw new \RuntimeException(self::class . ': Extension "' . OrmExtension::class . '" must be defined before this extension.');
		}

		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();

		$reader = $builder->addDefinition($this->prefix('annotationReader'))
			->setType(AnnotationReader::class)
			->setAutowired(false);

		Validators::assertField($config, 'ignore', 'array');

		foreach ($config['ignore'] as $annotationName) {
			$reader->addSetup('addGlobalIgnoredName', [$annotationName]);
			AnnotationReader::addGlobalIgnoredName($annotationName);
		}

		if ($config['cache'] === null) {
			$this->getDefaultCache()
				->setAutowired(false);
		} else {
			$builder->addDefinition($this->prefix('annotationsCache'))
				->setFactory($config['cache'])
				->setAutowired(false);
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
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();
		self::createEntityAnnotationManager($builder);

		$annotationDriver = $builder->addDefinition($this->prefix('annotationDriver'))
			->setFactory(AnnotationDriver::class);

		foreach ($config['paths'] as $userAnnotationPathNamespace => $userAnnotationPathPath) {
			self::addAnnotationPathToManager($builder, $userAnnotationPathNamespace, $userAnnotationPathPath);
		}
		if ($config['excludePaths'] !== []) {
			$annotationDriver->addSetup('addExcludePaths', [Helpers::expand($config['excludePaths'], $builder->parameters)]);
		}

		$tempDir = ($builder->parameters['tempDir'] ?? sys_get_temp_dir() . '/temp/doctrine') . '/result-cache';
		FileSystem::createDir($tempDir);

		/** @var ServiceDefinition $configurationDefinition */
		$configurationDefinition = $builder->getDefinitionByType(Configuration::class);
		$configurationDefinition->addSetup('setMetadataDriverImpl', [$this->prefix('@annotationDriver')]);
		$configurationDefinition->addSetup('?->setResultCacheImpl(new ' . FilesystemCache::class . '(?))', [
			'@self',
			FileSystem::normalizePath($tempDir),
		]);
	}


	public function afterCompile(ClassType $classType): void
	{
		$initialize = $classType->getMethod('initialize');
		$original = $initialize->getBody();
		$initialize->setBody('?::registerUniqueLoader(\'class_exists\');' . "\n", [new Literal(AnnotationRegistry::class)]);
		$initialize->addBody($original);
	}


	private function getDefaultCache(): ServiceDefinition
	{
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();

		if (!isset(self::Drivers[$config['defaultCache']])) {
			throw new InvalidStateException(sprintf('Unsupported default cache driver "%s"', $config['defaultCache']));
		}

		$driverCache = $builder->addDefinition($this->prefix('annotationsCache'))
			->setFactory(self::Drivers[$config['defaultCache']])
			->setAutowired(false);

		if ($config['defaultCache'] === 'filesystem') {
			$driverCache->setArguments([$builder->parameters['tempDir'] . '/cache/Doctrine.Annotations']);
		}

		return $driverCache;
	}
}
