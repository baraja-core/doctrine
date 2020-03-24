<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Ramsey\Uuid\Uuid;

class UuidBinaryGenerator extends AbstractIdGenerator
{
	/**
	 * @param EntityManager $em
	 * @param Entity|null $entity
	 * @return string
	 * @throws \Exception
	 */
	public function generate(EntityManager $em, $entity): string
	{
		return Uuid::uuid4()->getBytes();
	}
}