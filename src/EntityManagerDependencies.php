<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;

final class EntityManagerDependencies
{

	/** @var Connection */
	private $connection;

	/** @var Configuration */
	private $configuration;

	/** @var EventManager */
	private $eventManager;


	/**
	 * @param Connection $connection
	 * @param Configuration $configuration
	 * @param EventManager $eventManager
	 */
	public function __construct(Connection $connection, Configuration $configuration, EventManager $eventManager)
	{
		$this->connection = $connection;
		$this->configuration = $configuration;
		$this->eventManager = $eventManager;
	}


	/**
	 * @return Connection
	 */
	public function getConnection(): Connection
	{
		return $this->connection;
	}


	/**
	 * @return Configuration
	 */
	public function getConfiguration(): Configuration
	{
		return $this->configuration;
	}


	/**
	 * @return EventManager
	 */
	public function getEventManager(): EventManager
	{
		return $this->eventManager;
	}
}