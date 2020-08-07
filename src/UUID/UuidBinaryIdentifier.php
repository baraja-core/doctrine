<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


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


	public function getId(): string
	{
		return (string) $this->id;
	}


	final public function setId(?string $id = null): void
	{
		throw new \LogicException('Can not set identifier "' . $id . '", please use trait UuidIdentifier.');
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
