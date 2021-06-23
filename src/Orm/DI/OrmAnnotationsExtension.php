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
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Validators;

final class OrmAnnotationsExtension extends CompilerExtension
{
	/** @var string[] */
	private static array $annotationPaths = [];


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
		string $directoryPath
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
		self::createEntityAnnotationManager($builder);

		$annotationDriver = $builder->addDefinition($this->prefix('annotationDriver'))
			->setFactory(AnnotationDriver::class, [$this->prefix('@reader')]);

		foreach (self::$annotationPaths as $extensionAnnotationPathNamespace => $extensionAnnotationPathPath) {
			self::addAnnotationPathToManager($builder, $extensionAnnotationPathNamespace, $extensionAnnotationPathPath);
		}
		foreach ($config['paths'] ?? [] as $userAnnotationPathNamespace => $userAnnotationPathPath) {
			self::addAnnotationPathToManager($builder, $userAnnotationPathNamespace, $userAnnotationPathPath);
		}
		if (($config['excludePaths'] ?? []) !== []) {
			$annotationDriver->addSetup('addExcludePaths', [Helpers::expand($config['excludePaths'], $builder->parameters)]);
		}

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

		if (!isset(OrmCacheExtension::DRIVERS[$config['defaultCache']])) {
			throw new InvalidStateException(sprintf('Unsupported default cache driver "%s"', $config['defaultCache']));
		}

		$driverCache = $builder->addDefinition($this->prefix('annotationsCache'))
			->setFactory(OrmCacheExtension::DRIVERS[$config['defaultCache']])
			->setAutowired(false);

		if ($config['defaultCache'] === 'filesystem') {
			$driverCache->setArguments([$builder->parameters['tempDir'] . '/cache/Doctrine.Annotations']);
		}

		return $driverCache;
	}
}
