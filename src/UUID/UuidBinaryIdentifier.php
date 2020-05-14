<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


use Baraja\Doctrine\DatabaseException;
use Ramsey\Uuid\UuidInterface;

trait UuidBinaryIdentifier
{

	/**
	 * @var UuidInterface
	 * @ORM\Id
	 * @ORM\Column(type="uuid-binary", unique=true)
	 * @ORM\GeneratedValue(strategy="CUSTOM")
	 * @ORM\CustomIdGenerator(class="\Baraja\Doctrine\UUID\UuidBinaryGenerator")
	 */
	protected $id;


	/**
	 * @return string
	 */
	public function getId(): string
	{
		return (string) $this->id;
	}


	/**
	 * @param string|null $id
	 * @throws DatabaseException
	 */
	final public function setId(?string $id = null): void
	{
		DatabaseException::canNotSetIdentifier($id);
	}


	public function getBinaryId(): string
	{
		return $this->id->getBytes();
	}


	public function __clone()
	{
		$this->id = null;
	}
}
