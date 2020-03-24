<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Entity;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Nette\SmartObject;
use Nette\Utils\DateTime;

/**
 * @ORM\Entity()
 * @ORM\Table(
 *    name="core__database_slow_query",
 *    indexes={
 *       @Index(name="database_slow_query__hash", columns={"hash"}),
 *       @Index(name="database_slow_query__id_hash", columns={"id", "hash"})
 *    }
 * )
 */
class SlowQuery
{
	use UuidIdentifier;
	use SmartObject;

	/**
	 * @var string
	 * @ORM\Column(type="text")
	 */
	private $query;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	private $duration;

	/**
	 * @var string
	 * @ORM\Column(type="string", length=32, unique=true)
	 */
	private $hash;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	private $insertedDate;


	/**
	 * @param string $sql
	 * @param string $hash
	 * @param float $duration
	 */
	public function __construct(string $sql, string $hash, float $duration)
	{
		$this->query = $sql;
		$this->duration = $duration;
		$this->hash = $hash;
		$this->insertedDate = DateTime::from('now');
	}


	/**
	 * @return string
	 */
	public function getQuery(): string
	{
		return $this->query;
	}


	/**
	 * @return float
	 */
	public function getDuration(): float
	{
		return $this->duration;
	}


	/**
	 * @return string
	 */
	public function getHash(): string
	{
		return $this->hash;
	}


	/**
	 * @return \DateTime
	 */
	public function getInsertedDate(): \DateTime
	{
		return $this->insertedDate;
	}
}
