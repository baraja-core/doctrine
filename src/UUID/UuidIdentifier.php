<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


trait UuidIdentifier
{

	/**
	 * @var string|null
	 * @ORM\Id
	 * @ORM\Column(type="uuid", unique=true)
	 * @ORM\GeneratedValue(strategy="CUSTOM")
	 * @ORM\CustomIdGenerator(class="\Baraja\Doctrine\UUID\UuidGenerator")
	 */
	protected $id;


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
		$this->id = null;
	}
}
