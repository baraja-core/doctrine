<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM\Mapping;


/**
 * This is a universal service for storing mapping information of Doctrine entities and their physical paths on disk.
 * This service is defined at the beginning of the extension compilation and gradually expands in other extensions.
 * To add a new entity or group of entities for mapping, use OrmAnnotationsExtension::addAnnotationPathToManager().
 *
 * @internal
 */
final class EntityAnnotationManager
{
	/** @var string[] */
	private array $paths;


	/**
	 * @return string[]
	 */
	public function getPaths(): array
	{
		return $this->paths;
	}


	public function addPath(string $namespace, string $directoryPath): void
	{
		if (\is_dir($directoryPath) === false) {
			throw new \RuntimeException('Path "' . $directoryPath . '" is not valid directory.');
		}
		if (isset($this->paths[$namespace]) === true) {
			throw new \RuntimeException('Definition for namespace "' . $namespace . '" already exist (entity "' . $this->paths[$namespace] . '").');
		}

		$this->paths[$namespace] = $directoryPath;
	}
}
