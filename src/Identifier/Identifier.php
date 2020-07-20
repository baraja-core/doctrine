<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Identifier;


use Baraja\Doctrine\DatabaseException;
use Doctrine\ORM\Mapping as ORM;

trait Identifier
{

	/**
	 * @var int|null
	 * @ORM\Id
	 * @ORM\Column(type="integer", unique=true)
	 * @ORM\GeneratedValue
	 */
	protected $id;


	public function getId(): ?int
	{
		if ($this->id === null) {
			throw new \RuntimeException('Entity ID does not exist yet. Did you call ->persist() method first?');
		}

		return (int) $this->id;
	}


	/**
	 * @param int|null $id
	 * @throws DatabaseException
	 */
	public function setId(?int $id = null): void
	{
		DatabaseException::canNotSetIdentifier((string) $id);
	}


	public function dangerouslySetLegacyId(int $id): void
	{
		$this->id = $id;
		throw new \RuntimeException('The ID was passed unsafely. Please catch this exception if it was intended.');
	}


	public function __clone()
	{
		$this->id = null;
	}
}
