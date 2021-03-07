<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Bridge;


use Baraja\ServiceMethodInvoker\ProjectEntityRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineEntityRepository implements ProjectEntityRepository
{
	public function __construct(
		private EntityManagerInterface $entityManager
	) {
	}


	public function find(string $className, int|string $id): ?object
	{
		/** @phpstan-ignore-next-line */
		return $this->entityManager->find($className, $id);
	}
}
