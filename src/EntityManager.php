<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\Doctrine\DBAL\Tracy\QueryPanel\QueryPanel;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Cache;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\PessimisticLockException;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\TransactionRequiredException;
use Doctrine\ORM\UnitOfWork;
use Nette\Utils\FileSystem;
use Tracy\Debugger;

/**
 * Improved implementation of EntityManager with configure cache automatically and add data types.
 */
final class EntityManager implements EntityManagerInterface
{

	/** @var Connection */
	private $connection;

	/** @var Configuration */
	private $configuration;

	/** @var EventManager */
	private $eventManager;

	/** @var EntityManagerDependenciesAccessor */
	private $dependencies;


	/**
	 * @param EntityManagerDependenciesAccessor $dependencies
	 */
	public function __construct(EntityManagerDependenciesAccessor $dependencies)
	{
		$this->dependencies = $dependencies;
	}


	/**
	 * @internal reserved for DIC
	 * @param callable $callback with value (self $entityManager): void
	 */
	public function addInit(callable $callback): void
	{
		$this->dependencies->get()->addInitEvent($callback);
	}


	/**
	 * Adds an event listener that listens on the specified events.
	 *
	 * @param string|string[] $events The event(s) to listen on.
	 * @param object $listener The listener object.
	 * @return EntityManager
	 */
	public function addEventListener($events, $listener): self
	{
		if ($this->connection === null) {
			$this->dependencies->get()->addLazyEventListener([$events, $listener]);
		} else {
			$this->getEventManager()->addEventListener($events, $listener);
		}

		return $this;
	}


	/**
	 * @internal
	 */
	public function init(): void
	{
		if ($this->connection === null) {
			if (\class_exists(Debugger::class) === true) {
				Debugger::getBlueScreen()->addPanel([TracyBlueScreenDebugger::class, 'render']);
				TracyBlueScreenDebugger::setEntityManager($this);
			}
			$this->connection = ($manager = $this->dependencies->get())->getConnection();
			$this->configuration = $manager->getConfiguration();
			$this->eventManager = $manager->getEventManager();

			foreach ($manager->getInitEvents() as $initCallback) {
				$initCallback($this);
			}

			foreach ($manager->getLazyEventListeners() as $eventListener) {
				$this->getEventManager()->addEventListener($eventListener[0], $eventListener[1]);
			}

			$manager->setInitClosed();
		}
	}


	/**
	 * @internal
	 * @return string
	 */
	public function getDbDirPath(): string
	{
		static $cache;

		if ($cache === null) {
			FileSystem::createDir($dir = \dirname(__DIR__, 4) . '/temp/cache/baraja.doctrine');
			$cache = $dir . '/doctrine.db';
		}

		return $cache;
	}


	/**
	 * @internal
	 */
	public function fixDbDirPathPermission(): void
	{
		if (is_file($path = $this->getDbDirPath()) === true && fileperms($path) < 33204) {
			chmod($path, 0664);
		}
	}


	/**
	 * @param object $entity
	 * @return EntityManager
	 */
	public function persist($entity): self
	{
		try {
			$this->em()->persist($entity);
		} catch (ORMException $e) {
			EntityManagerException::e($e);
		}

		return $this;
	}


	/**
	 * @param object|mixed[]|null $entity
	 * @return EntityManager
	 */
	public function flush($entity = null): self
	{
		try {
			$this->em()->flush($entity);
		} catch (ORMException | OptimisticLockException $e) {
			EntityManagerException::e($e);
		}

		return $this;
	}


	/**
	 * @param string $className The class name of the object to find.
	 * @param mixed $id The identity of the object to find.
	 * @return object|null The found object.
	 */
	public function find($className, $id)
	{
		try {
			return $this->em()->find($className, $id);
		} catch (ORMException | OptimisticLockException | TransactionRequiredException $e) {
			throw new EntityManagerException($e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * @param object $object The object instance to remove.
	 * @return EntityManager
	 */
	public function remove($object): self
	{
		try {
			$this->em()->remove($object);
		} catch (ORMException $e) {
			EntityManagerException::e($e);
		}

		return $this;
	}


	/**
	 * @param object $object
	 * @return object
	 * @throws EntityManagerException
	 */
	public function merge($object)
	{
		try {
			return $this->em()->merge($object);
		} catch (ORMException $e) {
			throw new EntityManagerException($e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * @param string|mixed|null $objectName if given, only objects of this type will get detached.
	 * @return void
	 * @throws MappingException
	 */
	public function clear($objectName = null): void
	{
		if ($objectName !== null && \is_string($objectName) === false) {
			$objectName = \get_class($objectName);
		}
		$this->em()->clear($objectName);
	}


	/**
	 * @param object $object The object to detach.
	 * @return void
	 */
	public function detach($object): void
	{
		$this->em()->detach($object);
	}


	/**
	 * @param object $object The object to refresh.
	 * @return void
	 * @throws EntityManagerException
	 */
	public function refresh($object): void
	{
		try {
			$this->em()->refresh($object);
		} catch (ORMException $e) {
			throw new EntityManagerException($e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * @param string $className
	 * @return Repository
	 */
	public function getRepository($className): Repository
	{
		return new Repository(
			$em = $this->em(),
			$em->getClassMetadata($className)
		);
	}


	/**
	 * @param string $className
	 * @return ClassMetadata
	 */
	public function getClassMetadata($className): ClassMetadata
	{
		return $this->em()->getClassMetadata($className);
	}


	/**
	 * @return ClassMetadataFactory
	 */
	public function getMetadataFactory(): ClassMetadataFactory
	{
		return $this->em()->getMetadataFactory();
	}


	/**
	 * @param object $obj
	 * @return void
	 */
	public function initializeObject($obj): void
	{
		$this->em()->initializeObject($obj);
	}


	/**
	 * @param object $object
	 * @return bool
	 */
	public function contains($object): bool
	{
		return $this->em()->contains($object);
	}


	/**
	 * @return Cache|null
	 */
	public function getCache(): ?Cache
	{
		return $this->em()->getCache();
	}


	/**
	 * @return Connection
	 */
	public function getConnection(): Connection
	{
		return $this->em()->getConnection();
	}


	/**
	 * @return Query\Expr
	 */
	public function getExpressionBuilder(): Query\Expr
	{
		return $this->em()->getExpressionBuilder();
	}


	/**
	 * @return void
	 */
	public function beginTransaction(): void
	{
		$this->em()->beginTransaction();
	}


	/**
	 * @param callable $func The function to execute transactionally.
	 * @return mixed The non-empty value returned from the closure or true instead.
	 * @throws EntityManagerException
	 */
	public function transactional($func)
	{
		try {
			return $this->em()->transactional($func);
		} catch (\Throwable $e) {
			throw new EntityManagerException($e->getMessage(), $e->getCode(), $e);
		}
	}


	public function commit(): void
	{
		$this->em()->commit();
	}


	public function rollback(): void
	{
		$this->em()->rollback();
	}


	/**
	 * @param string $dql The DQL string.
	 * @return Query
	 */
	public function createQuery($dql = ''): Query
	{
		return $this->em()->createQuery($dql ?? '');
	}


	/**
	 * @param string $name
	 * @return Query
	 */
	public function createNamedQuery($name): Query
	{
		return $this->em()->createNamedQuery($name);
	}


	/**
	 * @param string $sql
	 * @param ResultSetMapping $rsm The ResultSetMapping to use.
	 * @return NativeQuery
	 */
	public function createNativeQuery($sql, ResultSetMapping $rsm): NativeQuery
	{
		return $this->em()->createNativeQuery($sql, $rsm);
	}


	/**
	 * @param string $name
	 * @return NativeQuery
	 */
	public function createNamedNativeQuery($name): NativeQuery
	{
		return $this->em()->createNamedNativeQuery($name);
	}


	/**
	 * @return QueryBuilder
	 */
	public function createQueryBuilder(): QueryBuilder
	{
		return $this->em()->createQueryBuilder();
	}


	/**
	 * @param string $entityName The name of the entity type.
	 * @param mixed $id The entity identifier.
	 * @return object|null The entity reference.
	 * @throws EntityManagerException
	 */
	public function getReference($entityName, $id)
	{
		try {
			return $this->em()->getReference($entityName, $id);
		} catch (ORMException $e) {
			throw new EntityManagerException($e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * @param string $entityName The name of the entity type.
	 * @param mixed $identifier The entity identifier.
	 * @return object|null The (partial) entity reference.
	 */
	public function getPartialReference($entityName, $identifier)
	{
		return $this->em()->getPartialReference($entityName, $identifier);
	}


	/**
	 * @return void
	 */
	public function close(): void
	{
		$this->em()->close();
	}


	/**
	 * @param object $entity The entity to copy.
	 * @param bool $deep FALSE for a shallow copy, TRUE for a deep copy.
	 * @return object The new entity.
	 * @throws EntityManagerException
	 */
	public function copy($entity, $deep = false)
	{
		try {
			return $this->em()->copy($entity, $deep);
		} catch (\BadMethodCallException $e) {
			throw new EntityManagerException($e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * @param object $entity
	 * @param int $lockMode
	 * @param int|null $lockVersion
	 * @return void
	 * @throws OptimisticLockException|PessimisticLockException
	 */
	public function lock($entity, $lockMode, $lockVersion = null): void
	{
		$this->em()->lock($entity, $lockMode, $lockVersion);
	}


	/**
	 * @return EventManager
	 */
	public function getEventManager(): EventManager
	{
		return $this->em()->getEventManager();
	}


	/**
	 * @return Configuration
	 */
	public function getConfiguration(): Configuration
	{
		return $this->em()->getConfiguration();
	}


	/**
	 * @return bool
	 */
	public function isOpen(): bool
	{
		return $this->em()->isOpen();
	}


	/**
	 * @return UnitOfWork
	 */
	public function getUnitOfWork(): UnitOfWork
	{
		return $this->em()->getUnitOfWork();
	}


	/**
	 * @deprecated
	 * @param string|int $hydrationMode
	 * @return AbstractHydrator
	 */
	public function getHydrator($hydrationMode): AbstractHydrator
	{
		trigger_error(__METHOD__ . ': Method getHydrator() is deprecated, use DIC.', E_DEPRECATED);

		return $this->em()->getHydrator($hydrationMode);
	}


	/**
	 * @param string|int $hydrationMode
	 * @return AbstractHydrator
	 * @throws EntityManagerException
	 */
	public function newHydrator($hydrationMode): AbstractHydrator
	{
		try {
			return $this->em()->newHydrator($hydrationMode);
		} catch (ORMException $e) {
			throw new EntityManagerException($e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * @return ProxyFactory
	 */
	public function getProxyFactory(): ProxyFactory
	{
		return $this->em()->getProxyFactory();
	}


	/**
	 * @return Query\FilterCollection
	 */
	public function getFilters(): Query\FilterCollection
	{
		return $this->em()->getFilters();
	}


	/**
	 * @return bool -> True, if the filter collection is clean.
	 */
	public function isFiltersStateClean(): bool
	{
		return $this->em()->isFiltersStateClean();
	}


	/**
	 * @return bool -> True, if the EM has a filter collection.
	 */
	public function hasFilters(): bool
	{
		return $this->em()->hasFilters();
	}


	/**
	 * @param CacheProvider|null $cache
	 */
	public function setCache(?CacheProvider $cache = null): void
	{
		$this->init();
		QueryPanel::setCache($cache);

		if ($cache === null) {
			trigger_error(
				'Doctrine cache is not available. Application will run slowly!' . "\n"
				. 'Please install ApcuCache (function "apcu_cache_info()") or SQLite3 (function "sqlite_open()").',
				E_USER_WARNING
			);
		} else {
			$cache->setNamespace(md5(__FILE__));
			$this->configuration->setMetadataCacheImpl($cache);
			$this->configuration->setQueryCacheImpl($cache);
		}

		$this->configuration->setAutoGenerateProxyClasses(2);
	}


	/**
	 * @param bool $saveMode
	 * @param bool $invalidCache
	 */
	public function buildCache(bool $saveMode = false, $invalidCache = false): void
	{
		$this->init();
		QueryPanel::setInvalidCache($invalidCache);

		if ($invalidCache === true) {
			if (empty($metadata = $this->getMetadataFactory()->getAllMetadata())) {
				return;
			}

			if (empty(($schemaTool = new SchemaTool($this))->getUpdateSchemaSql($metadata, $saveMode))) {
				return;
			}

			$schemaTool->updateSchema($metadata, $saveMode);
		}
	}


	/**
	 * @return \Doctrine\ORM\EntityManager
	 */
	private function em(): \Doctrine\ORM\EntityManager
	{
		static $cache;

		if ($cache === null) {
			$this->init();
			try {
				$cache = \Doctrine\ORM\EntityManager::create($this->connection, $this->configuration, $this->eventManager);
			} catch (ORMException $e) {
				Debugger::log($e);
				trigger_error($e->getMessage(), E_ERROR);
			}
		}

		return $cache;
	}
}
