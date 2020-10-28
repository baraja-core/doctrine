<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM\Entity\Attributes;


trait Id
{

	/**
	 * @ORM\Column(type="integer", nullable=FALSE)
	 * @ORM\Id
	 * @ORM\GeneratedValue
	 */
	private int $id;


	public function getId(): int
	{
		return $this->id;
	}


	public function __clone()
	{
		throw new \LogicException('Entity "' . $this->getId() . '" can not be cloned.');
	}
}
