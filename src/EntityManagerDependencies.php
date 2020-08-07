<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;

final class EntityManagerDependencies
{

	/** @var bool */
	private $initClosed = false;

	/** @var Connection */
	private $connection;

	/** @var Configuration */
	private $configuration;

	/** @var EventManager */
	private $eventManager;

	/**
	 * Init events will be called when EntityManager will be connected to database.
	 * Each EntityManager instance can use custom init events.
	 *
	 * @var callable[]
	 */
	private $initEvents = [];

	/**
	 * List of waiting lazy event listeners.
	 * This property must be static, because EntityManager can be created in multiple instances.
	 *
	 * @var mixed[][]
	 */
	private $lazyEventListeners = [];


	public function __construct(Connection $connection, Configuration $configuration, EventManager $eventManager)
	{
		$this->connection = $connection;
		$this->configuration = $configuration;
		$this->eventManager = $eventManager;
	}


	public function getConnection(): Connection
	{
		return $this->connection;
	}


	public function getConfiguration(): Configuration
	{
		return $this->configuration;
	}


	public function getEventManager(): EventManager
	{
		return $this->eventManager;
	}


	/**
	 * @internal for init process
	 * @return callable[]
	 */
	public function getInitEvents(): array
	{
		if ($this->initClosed === true) {
			throw new \RuntimeException('EntityManager was already instanced.');
		}

		return $this->initEvents;
	}


	public function addInitEvent(callable $event): void
	{
		$this->initEvents[] = $event;
	}


	/**
	 * @internal for init process
	 * @return mixed[][]
	 */
	public function getLazyEventListeners(): array
	{
		if ($this->initClosed === true) {
			throw new \RuntimeException('EntityManager was already instanced.');
		}

		return $this->lazyEventListeners;
	}


	/**
	 * @param mixed[] $lazyEventListener
	 */
	public function addLazyEventListener(array $lazyEventListener): void
	{
		$this->lazyEventListeners[] = $lazyEventListener;
	}


	/**
	 * Mark EntityManager as instanced and successfully closed.
	 */
	public function setInitClosed(): void
	{
		$this->initClosed = true;
	}
}
