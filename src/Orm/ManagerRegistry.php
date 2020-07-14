<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM;


use Doctrine\Common\Proxy\Proxy;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\AbstractManagerRegistry;
use Nette\DI\Container;

class ManagerRegistry extends AbstractManagerRegistry
{

	/** @var Container */
	private $container;


	/**
	 * @param Connection $connection
	 * @param EntityManagerInterface $em
	 * @param Container $container
	 */
	public function __construct(Connection $connection, EntityManagerInterface $em, Container $container)
	{
		$defaultConnection = $container->findByType(get_class($connection))[0];
		$defaultManager = $container->findByType(get_class($em))[0];

		$connections = ['default' => $defaultConnection];
		$managers = ['default' => $defaultManager];

		parent::__construct('ORM', $connections, $managers, 'default', 'default', Proxy::class);
		$this->container = $container;
	}


	/**
	 * @param string $alias
	 * @throws ORMException
	 * @return string
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function getAliasNamespace($alias): string
	{
		foreach (array_keys($this->getManagers()) as $name) {
			try {
				/** @var EntityManagerInterface $entityManager */
				$entityManager = $this->getManager($name);

				return $entityManager->getConfiguration()->getEntityNamespace($alias);
			} catch (ORMException $e) {
			}
		}

		throw ORMException::unknownEntityNamespace($alias);
	}


	/**
	 * @param string $name
	 * @return object
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint
	 */
	protected function getService($name)
	{
		return $this->container->getService($name);
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
