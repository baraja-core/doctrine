<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class UuidBinaryGenerator extends AbstractIdGenerator
{

	/**
	 * @param Entity|null $entity
	 */
	public function generate(EntityManager $em, $entity): UuidInterface
	{
		try {
			return Uuid::uuid4();
		} catch (\Throwable $e) {
			throw new \RuntimeException('Can not generate UUID: ' . $e->getMessage(), $e->getCode(), $e);
		}
	}
}
