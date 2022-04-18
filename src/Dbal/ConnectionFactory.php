<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL;


use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class ConnectionFactory
{
	/** @var array<int, string> */
	private array $commentedTypes = [];

	private bool $initialized = false;


	/**
	 * @param array<string, array{class: class-string<Type>, commented: string|null}> $typesConfig
	 * @param array<string, class-string> $typesMapping
	 */
	public function __construct(
		private array $typesConfig = [],
		private array $typesMapping = [],
	) {
	}


	/**
	 * Create a connection by name.
	 *
	 * @param mixed[] $params
	 */
	public function createConnection(
		array $params,
		?Configuration $config = null,
		?EventManager $eventManager = null,
	): Connection {
		if (!$this->initialized) {
			$this->initializeTypes();
		}

		$connection = DriverManager::getConnection($params, $config, $eventManager);
		if ($this->typesMapping !== []) {
			$platform = $this->getDatabasePlatform($connection);
			foreach ($this->typesMapping as $dbType => $doctrineType) {
				$platform->registerDoctrineTypeMapping($dbType, $doctrineType);
			}
		}
		if ($this->commentedTypes !== []) {
			$platform = $this->getDatabasePlatform($connection);
			foreach ($this->commentedTypes as $type) {
				$platform->markDoctrineTypeCommented(Type::getType($type));
			}
		}

		return $connection;
	}


	/**
	 * Try to get the database platform.
	 *
	 * This could fail if types should be registered to an predefined/unused connection
	 * and the platform version is unknown.
	 * For details have a look at DoctrineBundle issue #673.
	 */
	private function getDatabasePlatform(Connection $connection): AbstractPlatform
	{
		try {
			return $connection->getDatabasePlatform();
		} catch (\Throwable $e) {
			if ($e instanceof DriverException) {
				throw new \RuntimeException(
					'An exception occurred while establishing a connection to figure out your platform version.' . PHP_EOL .
					'You can circumvent this by setting a \'server_version\' configuration value' . PHP_EOL . PHP_EOL .
					'For further information have a look at:' . PHP_EOL .
					'https://github.com/doctrine/DoctrineBundle/issues/673',
					0,
					$e,
				);
			}

			throw $e;
		}
	}


	private function initializeTypes(): void
	{
		foreach ($this->typesConfig as $type => $typeConfig) {
			if (Type::hasType($type)) {
				Type::overrideType($type, $typeConfig['class']);
			} else {
				Type::addType($type, $typeConfig['class']);
			}
			if ($typeConfig['commented'] !== null) {
				$this->commentedTypes[] = $type;
			}
		}

		$this->initialized = true;
	}
}
