<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


use Baraja\Doctrine\DatabaseException;

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


	/**
	 * @return string|null
	 */
	public function getId(): ?string
	{
		if ($this->id === null) {
			throw new \RuntimeException('Entity ID does not exist yet. Did you call ->persist() method first?');
		}

		return (string) $this->id;
	}


	/**
	 * @param string|null $id
	 * @throws DatabaseException
	 */
	public function setId(?string $id = null): void
	{
		DatabaseException::canNotSetIdentifier($id);
	}


	/**
	 * Back support for migration logic.
	 *
	 * @param string $id
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
