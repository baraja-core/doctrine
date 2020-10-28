<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Identifier;


use Doctrine\ORM\Mapping as ORM;

trait Identifier
{

	/**
	 * @ORM\Id
	 * @ORM\Column(type="integer", unique=true)
	 * @ORM\GeneratedValue
	 */
	protected ?int $id;


	public function getId(): ?int
	{
		if ($this->id === null) {
			throw new \RuntimeException('Entity ID does not exist yet. Did you call ->persist() method first?');
		}

		return (int) $this->id;
	}


	public function setId(?int $id = null): void
	{
		throw new \LogicException('Can not set identifier "' . $id . '", please use trait UuidIdentifier.');
	}


	public function dangerouslySetLegacyId(int $id): void
	{
		$this->id = $id;
		throw new \RuntimeException('The ID was passed unsafely. Please catch this exception if it was intended.');
	}


	public function __clone()
	{
		throw new \LogicException('Entity "' . $this->getId() . '" can not be cloned.');
	}
}
