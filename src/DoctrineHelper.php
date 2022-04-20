<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\Persistence\Mapping\MappingException;
use Tracy\Debugger;
use Tracy\ILogger;

class DoctrineHelper
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	/**
	 * Return list of class names which is variant of given entity.
	 * By $exclude you can define list of entities which will be skipped.
	 *
	 * @param class-string $entity
	 * @param array<int, class-string>|null $exclude
	 * @return array<class-string, string> (type => name)
	 */
	public function getEntityVariants(string $entity, ?array $exclude = null): array
	{
		$meta = $this->entityManager->getClassMetadata($entity);
		$return = [];
		if (\is_array($meta->discriminatorMap) && \count($meta->discriminatorMap) > 0) {
			foreach ($meta->discriminatorMap as $variant) {
				$variant = (string) $variant;
				if (class_exists($variant) === false) {
					throw new \LogicException(sprintf('Entity class "%s" does not exist.', $variant));
				}
				try {
					$return[$variant] = (string) Utils::reflectionClassDocComment($variant, 'name');
				} catch (\ReflectionException) {
					$return[$variant] = (string) preg_replace_callback(
						'/([a-z0-9])([A-Z])/',
						static fn(array $match) => $match[1] . ' ' . strtolower($match[2]),
						(string) preg_replace('/^.*?\\\\([^\\\\]+)$/', '$1', $variant),
					);
				}
			}
		}

		foreach ($exclude ?? [] as $excludeItem) {
			unset($return[$excludeItem]);
		}

		return $return;
	}


	/**
	 * Return most embedded entity.
	 * In case of `CustomProduct` extends `Product` extends `BaseProduct`, return `CustomProduct`.
	 *
	 * @param class-string $entityClassName
	 * @return class-string
	 */
	public function getBestOfType(string $entityClassName): string
	{
		$variants = $this->getEntityVariants($entityClassName);
		if (\in_array(\count($variants), [0, 1], true) === true) {
			return $entityClassName;
		}

		$topLength = 0;
		$topType = $entityClassName;
		foreach (array_keys($variants) as $variant) {
			try {
				$length = $this->getParentClassLength(Utils::getReflectionClass($variant));
				if ($length > $topLength) {
					$topLength = $length;
					$topType = $variant;
				}
			} catch (\ReflectionException) {
				// Silence is golden.
			}
		}

		return $topType;
	}


	/**
	 * Return real table name by entity Class name.
	 *
	 * @param class-string $entity
	 */
	public function getTableNameByEntity(string $entity): string
	{
		return $this->entityManager->getClassMetadata($entity)->table['name'];
	}


	/**
	 * If extends lot's of entities, return root entity class name.
	 *
	 * @param class-string $entity
	 */
	public function getRootEntityName(string $entity): string
	{
		return $this->entityManager->getClassMetadata($entity)->rootEntityName;
	}


	/**
	 * In case of chain inheritance Doctrine store lot's of entities in one table
	 * and distinguishes itself by `discriminator` column.
	 * This method return discriminator column name by given entity Class name.
	 *
	 * @param class-string $entity
	 */
	public function getDiscriminatorByEntity(string $entity): string
	{
		/** @return array<string, string> */
		$loadDiscriminatorMap = function (string $entity): array {
			$map = $this->entityManager->getClassMetadata($entity)->discriminatorMap ?? [];
			assert(is_array($map));

			return $map;
		};

		foreach ($loadDiscriminatorMap($entity) as $discriminator => $variant) {
			if ($variant === $entity) {
				return $discriminator;
			}
		}

		$entity = $this->getRootEntityName($entity);
		foreach ($loadDiscriminatorMap($entity) as $discriminator => $variant) {
			if ($variant === $entity) {
				return $discriminator;
			}
		}

		return '';
	}


	/**
	 * Elevate the type of entity to the best possible and return as new type.
	 * This method can fail if you are tried save missing required columns.
	 * For more information please follow exception messages.
	 *
	 * @throws DatabaseException
	 */
	public function remapEntityToBestType(object $from): ?object
	{
		$fromType = $from::class;
		$bestType = $this->getBestOfType($fromType);
		if ($fromType === $bestType) {
			return $from;
		}

		return $this->remapEntity($from, $bestType);
	}


	/**
	 * Remap one type of entity to new type in database.
	 * This feature is not supported in Doctrine therefore, the change may not be stable.
	 * When you remap entity type, please select all data again, because Doctrine internal memory can be damaged.
	 * Best practice is refresh page or break CLI process after this change.
	 *
	 * @param object $from instance of specific entity
	 * @param object|class-string $to instance of specific entity or class-name
	 * @throws DatabaseException
	 */
	public function remapEntity(object $from, object|string $to): ?object
	{
		$toType = is_object($to) ? $to::class : $to;
		$toDiscriminator = $this->getDiscriminatorByEntity($toType);
		if ($this->getDiscriminatorByEntity($from::class) === $toDiscriminator) {
			return $from;
		}

		$fromTable = ($fromMetaData = $this->entityManager->getClassMetadata($from::class))->getTableName();
		$toTable = $this->entityManager->getClassMetadata($toType)->getTableName();
		$discriminatorColumn = $fromMetaData->discriminatorColumn['fieldName'] ?? '?';

		if ($fromTable !== $toTable) {
			throw new DatabaseException(
				'Entities for remap must be same table type, '
				. '"' . $fromTable . '" and "' . $to . '" given.',
			);
		}

		try {
			$this->entityManager->clear($this->getRootEntityName($from::class));
		} catch (MappingException $e) {
			Debugger::log($e, ILogger::CRITICAL);
			throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
		}

		if (method_exists($from, 'getId')) {
			$id = $from->getId();
		} else {
			throw new \InvalidArgumentException('Entity "' . $from::class . '" do not contain required method getId().');
		}

		try {
			$this->entityManager->getConnection()->executeStatement(
				str_replace(
					['{table}', '{discriminatorColumn}', '{discriminator}', '{id}'],
					[$fromTable, $discriminatorColumn, $toDiscriminator, $id],
					'UPDATE `{table}` '
					. 'SET `{discriminatorColumn}` = \'{discriminator}\' '
					. 'WHERE `id` = \'{id}\'',
				),
			);
		} catch (\Throwable $e) {
			Debugger::log($e, ILogger::CRITICAL);
			trigger_error($e->getMessage());
		}

		return $this->entityManager->getRepository($toType)->find($id);
	}


	/**
	 * Return relative direction between given entity and root entity by exploring parents.
	 *
	 * In case of `CustomProduct` extends `Product` extends `BaseProduct`, return:
	 * for CustomProduct -> 3
	 * for Product       -> 2
	 * for BaseProduct   -> 1
	 */
	private function getParentClassLength(\ReflectionClass $reflection, int $bind = 1): int
	{
		$length = 0;
		$parent = $reflection;
		do {
			if ($parent === false) {
				break;
			}
			$parent = $parent->getParentClass();
			$length++;
		} while (true);

		return $length + max($bind, 0);
	}
}
