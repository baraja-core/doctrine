<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class UuidBinaryGenerator extends AbstractIdGenerator
{
	/**
	 * @param EntityManager $em
	 * @param Entity|null $entity
	 * @return UuidInterface
	 * @throws \Exception
	 */
	public function generate(EntityManager $em, $entity): UuidInterface
	{
		return Uuid::uuid4();
	}
}