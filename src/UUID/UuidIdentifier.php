<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

trait UuidIdentifier
{
	/**
	 * @ORM\Id
	 * @ORM\Column(type="uuid", unique=true)
	 * @ORM\GeneratedValue(strategy="CUSTOM")
	 * @ORM\CustomIdGenerator(class="\Baraja\Doctrine\UUID\UuidGenerator")
	 */
	#[Id]
	#[Column(type: 'uuid', unique: true)]
	#[GeneratedValue(strategy: 'CUSTOM')]
	#[CustomIdGenerator(class: UuidGenerator::class)]
	protected ?string $id;


	public function getId(): ?string
	{
		if ($this->id === null) {
			throw new \RuntimeException('Entity ID does not exist yet. Did you call ->persist() method first?');
		}

		return (string) $this->id;
	}


	public function setId(?string $id = null): void
	{
		throw new \LogicException('Can not set identifier "' . $id . '", please use trait UuidIdentifier.');
	}


	/**
	 * Back support for migration logic.
	 */
	public function dangerouslySetLegacyId(string $id): void
	{
		$this->id = $id;
		throw new \RuntimeException('The ID was passed unsafely. Please catch this exception if it was intended.');
	}


	public function __clone()
	{
		throw new \LogicException('Entity "' . $this->getId() . '" can not be cloned.');
	}
}
