<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM\Mapping;


use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\AttributeReader;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\AnnotationDriver as AbstractAnnotationDriver;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

final class AnnotationDriver extends AbstractAnnotationDriver
{
	/** @var AbstractAnnotationDriver[] */
	private array $drivers;

	private AbstractAnnotationDriver $defaultDriver;

	/** @var array<string, int> */
	private array $entityToDriver;


	/**
	 * @param array<string, string>|null $paths
	 */
	public function __construct(
		EntityAnnotationManager $annotationManager,
		?array $paths = null,
		?Storage $storage = null,
	) {
		$attributeReader = new AttributeReader;
		$annotationReader = new AnnotationReader;
		$paths ??= $annotationManager->getPaths();
		parent::__construct($annotationReader, $paths);
		$this->defaultDriver = new AttributeDriver($paths);
		/** @phpstan-ignore-next-line */
		$this->defaultDriver->reader = $attributeReader;
		$this->drivers = [
			$this->defaultDriver,
			new \Doctrine\ORM\Mapping\Driver\AnnotationDriver($annotationReader, $paths),
		];
		if ($storage !== null) {
			$cache = new Cache($storage, 'doctrine-annotation-driver');
			/** @var array<string, int>|null $classList */
			$classList = $cache->load('entityToDriver');
			if ($classList === null) {
				$classList = $this->computeClassList();
				$cache->save('entityToDriver', $classList, [Cache::EXPIRATION => '10 minutes']);
			}
		} else {
			$classList = $this->computeClassList();
		}
		$this->entityToDriver = $classList;
	}


	/**
	 * Loads the metadata for the specified class into the provided container.
	 *
	 * @param string $className
	 * @psalm-param class-string<T> $className
	 * @psalm-param ClassMetadata<T> $metadata
	 * @template T of object
	 */
	public function loadMetadataForClass($className, ClassMetadata $metadata): void
	{
		$this->getDriveByEntityClassName($className)
			->loadMetadataForClass($className, $metadata);
	}


	/**
	 * Gets the names of all mapped classes known to this driver.
	 *
	 * @return string[] The names of all mapped classes known to this driver.
	 * @psalm-return list<class-string>
	 */
	public function getAllClassNames(): array
	{
		$return = [];
		foreach ($this->drivers as $driver) {
			$return[] = $driver->getAllClassNames();
		}

		return array_unique(array_merge([], ...$return));
	}


	/**
	 * Returns whether the class with the specified name should have its metadata loaded.
	 * This is only the case if it is either mapped as an Entity or a MappedSuperclass.
	 *
	 * @param string $className
	 * @psalm-param class-string $className
	 */
	public function isTransient($className): bool
	{
		foreach ($this->drivers as $driver) {
			if ($driver->isTransient($className)) {
				return true;
			}
		}

		return false;
	}


	/**
	 * @return array<string, int>
	 */
	private function computeClassList(): array
	{
		$return = [];
		foreach ($this->getAllClassNames() as $className) {
			try {
				$ref = new \ReflectionClass($className);
				$return[$className] = $ref->getAttributes(Entity::class) !== [] ? 0 : 1;
			} catch (\Throwable $e) {
				throw new \RuntimeException('Entity "' . $className . '" is broken: ' . $e->getMessage(), 500, $e);
			}
		}

		return $return;
	}


	private function getDriveByEntityClassName(string $className): AbstractAnnotationDriver
	{
		return $this->drivers[$this->entityToDriver[$className] ?? 0] ?? $this->defaultDriver;
	}
}
