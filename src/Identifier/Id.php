<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM\Entity\Attributes;


/**
 * @deprecated since 20210-06-01 use Identifier trait instead.
 */
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
		trigger_error(__METHOD__ . ': Trait "Id" is deprecated since 20210-06-01 use Identifier trait instead.');

		return $this->id;
	}


	public function __clone()
	{
		throw new \LogicException('Entity "' . $this->getId() . '" can not be cloned.');
	}
}
