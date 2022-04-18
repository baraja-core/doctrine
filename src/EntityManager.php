<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\Doctrine\DBAL\Tracy\QueryPanel\QueryPanel;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\TransactionRequiredException;
use Doctrine\Persistence\Mapping\MappingException;
use Tracy\Debugger;

/**
 * Improved implementation of EntityManager with configure cache automatically and add data types.
 */
final class EntityManager extends \Doctrine\ORM\EntityManager
{
	public function __construct(
		private string $cacheDir,
		Connection $connection,
		private Configuration $configuration,
		EventManager $eventManager,
		?QueryPanel $panel = null,
	) {
		if ($panel !== null) {
			$panel->setEntityManager($this);
			$panel->setConnection($connection);
			Debugger::getBar()->addPanel($panel);
		}
		if (\class_exists(Debugger::class) === true) {
			Debugger::getBlueScreen()->addPanel([TracyBlueScreenDebugger::class, 'render']);
			TracyBlueScreenDebugger::setEntityManager($this);
			if ($panel !== null) {
				TracyBlueScreenDebugger::setPanel($panel);
			}
		}
		parent::__construct(
			$connection,
			$configuration,
			$eventManager,
		);
	}


	/**
	 * @deprecated since 2021-06-01 method will be moved to service.
	 * @param callable $callback with value (self $entityManager): void
	 * @internal reserved for DIC
	 */
	public function addInit(callable $callback): void
	{
		$callback($this);
	}


	/**
	 * Adds an event listener that listens on the specified events.
	 *
	 * @param string|string[] $events The event(s) to listen on.
	 * @param object $listener The listener object.
	 * @phpstan-return void
	 */
	public function addEventListener($events, $listener): self
	{
		$this->getEventManager()->addEventListener($events, $listener);

		return $this;
	}


	/**
	 * @deprecated since 2021-06-01 method will be moved to service.
	 * @internal
	 */
	public function getDbDirPath(): string
	{
		return $this->cacheDir . '/doctrine.db';
	}


	/**
	 * @deprecated since 2021-06-01 method will be moved to service.
	 * @internal
	 */
	public function fixDbDirPathPermission(): void
	{
		$cacheFile = $this->cacheDir . '/doctrine.db';
		if (is_file($cacheFile) === true && fileperms($cacheFile) < 33_204) {
			chmod($cacheFile, 0_664);
		}
	}


	/**
	 * @param object $entity
	 * @phpstan-return void
	 */
	public function persist($entity): self
	{
		try {
			parent::persist($entity);
		} catch (ORMException $e) {
			throw new EntityManagerException($e->getMessage(), $e->getCode(), $e);
		}

		return $this;
	}


	/**
	 * @param object|mixed[]|null $entity
	 * @phpstan-return void
	 */
	public function flush($entity = null): self
	{
		if ($entity !== null) {
			throw new \LogicException(
				'Calling flush() with any arguments to flush specific entities has been removed. '
				. 'Did you mean ->getUnitOfWork()->commit($entity)?',
			);
		}
		try {
			parent::flush($entity);
		} catch (ORMException | OptimisticLockException $e) {
			throw new EntityManagerException($e->getMessage(), $e->getCode(), $e);
		}

		return $this;
	}


	/**
	 * @param string $className The class name of the object to find.
	 * @param mixed $id The identity of the object to find.
	 * @param 0|1|2|4|null $lockMode One of the \Doctrine\DBAL\LockMode::* constants
	 *        or NULL if no specific lock mode should be used during the search.
	 * @param int|null $lockVersion The version of the entity to find when using ptimistic locking.
	 * @return object|null The found object.
	 */
	public function find($className, $id, $lockMode = null, $lockVersion = null)
	{
		if (\class_exists($className) === false) {
			throw new \InvalidArgumentException(
				'Entity name "' . $className . '" must be valid class name. Is your class autoloadable?',
			);
		}
		try {
			return parent::find($className, $id, $lockMode, $lockVersion);
		} catch (ORMException | OptimisticLockException | TransactionRequiredException $e) {
			throw new EntityManagerException($e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * @param string|object|null $objectName if given, only objects of this type will get detached.
	 * @throws EntityManagerException
	 */
	public function clear($objectName = null): void
	{
		if (is_object($objectName)) {
			$objectName = $objectName::class;
			trigger_error(sprintf('Object argument with type "%s" is deprecated since 2022-04-18, please use className.', $objectName), E_USER_DEPRECATED);
		}
		try {
			parent::clear($objectName);
		} catch (MappingException $e) {
			throw new EntityManagerException($e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * @param class-string $className
	 */
	public function getRepository($className): EntityRepository
	{
		try {
			$metadata = parent::getClassMetadata($className);
		} catch (\Doctrine\ORM\Mapping\MappingException $e) {
			if (is_subclass_of($className, EntityRepository::class)) {
				throw new \InvalidArgumentException(sprintf('Get repository for "%s" is not allowed, please use entity name.', $className), 500, $e);
			}
			throw $e;
		}
		$repository = $metadata->customRepositoryClassName ?? Repository::class;

		return new $repository($this, $metadata);
	}


	/**
	 * @param string $entityName The name of the entity type.
	 * @param mixed $id The entity identifier.
	 * @return object|null The entity reference.
	 * @throws EntityManagerException
	 */
	public function getReference($entityName, $id)
	{
		if (\class_exists($entityName) === false) {
			throw new \InvalidArgumentException(
				'Entity name "' . $entityName . '" must be valid class name. Is your class autoloadable?',
			);
		}
		try {
			return parent::getReference($entityName, $id);
		} catch (ORMException $e) {
			throw new EntityManagerException($e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * @deprecated since 2021-06-01 method will be moved to service.
	 * @internal
	 */
	public function setCache(?CacheProvider $cache = null): void
	{
		QueryPanel::setCache($cache);

		if ($cache === null) {
			trigger_error(
				'Doctrine cache is not available. Application will run slowly!' . "\n"
				. 'Please install ApcuCache (function "apcu_cache_info()") or SQLite3 (function "sqlite_open()").',
				E_USER_WARNING,
			);
		} else {
			$cache->setNamespace(md5(__FILE__));
			$this->configuration->setMetadataCacheImpl($cache);
			$this->configuration->setQueryCacheImpl($cache);
		}

		$this->configuration->setAutoGenerateProxyClasses(2);
	}


	/**
	 * @deprecated since 2021-06-01 method will be moved to service.
	 * @internal
	 */
	public function buildCache(bool $saveMode = false, bool $invalidCache = false): void
	{
		QueryPanel::setInvalidCache($invalidCache);
		if ($invalidCache === true) {
			$metadata = $this->getMetadataFactory()->getAllMetadata();
			/** @phpstan-ignore-next-line */
			if (empty($metadata)) {
				return;
			}
			$schemaTool = new SchemaTool($this);
			if ($schemaTool->getUpdateSchemaSql($metadata, $saveMode) === []) {
				return;
			}

			$schemaTool->updateSchema($metadata, $saveMode);
		}
	}
}
