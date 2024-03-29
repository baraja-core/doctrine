<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM;


use Doctrine\Common\Proxy\Proxy;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Nette\DI\Container;

final class ManagerRegistry extends AbstractManagerRegistry
{
	private Container $container;


	public function __construct(Connection $connection, EntityManagerInterface $em, Container $container)
	{
		$defaultConnection = $container->findByType($connection::class)[0];
		$defaultManager = $container->findByType($em::class)[0];

		$connections = ['default' => $defaultConnection];
		$managers = ['default' => $defaultManager];

		parent::__construct('ORM', $connections, $managers, 'default', 'default', Proxy::class);
		$this->container = $container;
	}


	/**
	 * @param string $alias
	 * @throws ORMException
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function getAliasNamespace($alias): string
	{
		foreach (array_keys($this->getManagers()) as $name) {
			try {
				/** @var EntityManagerInterface $entityManager */
				$entityManager = $this->getManager($name);

				return $entityManager->getConfiguration()->getEntityNamespace($alias);
			} catch (ORMException) {
				// Silence is golden.
			}
		}

		throw ORMException::unknownEntityNamespace($alias);
	}


	/**
	 * @param string $name
	 * @return ObjectManager
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint
	 */
	protected function getService($name)
	{
		$service = $this->container->getService($name);
		assert($service instanceof ObjectManager);

		return $service;
	}


	/**
	 * @param string $name
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	protected function resetService($name): void
	{
		$this->container->removeService($name);
	}
}
