<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


use Baraja\Doctrine\DatabaseException;

trait UuidBinaryIdentifier
{

	/**
	 * @var string|null
	 * @ORM\Id
	 * @ORM\Column(type="uuid", unique=true)
	 * @ORM\GeneratedValue(strategy="CUSTOM")
	 * @ORM\CustomIdGenerator(class="\Baraja\Doctrine\UUID\UuidBinaryGenerator")
	 */
	protected $id;

	/**
	 * @return string|null
	 */
	public function getId(): ?string
	{
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

	public function __clone()
	{
		$this->id = null;
	}

}