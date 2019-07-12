<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Entity;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use Nette\SmartObject;
use Nette\Utils\DateTime;

/**
 * @ORM\Entity()
 * @ORM\Table(name="core__database_slow_query")
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
	 * @param float $duration
	 */
	public function __construct(string $sql, float $duration)
	{
		$this->query = $sql;
		$this->duration = $duration;
		$this->hash = $this->computeHash($this->simplifyQuery($sql));
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

	/**
	 * @param string $query
	 * @return string
	 */
	public function simplifyQuery(string $query): string
	{
		return (string) preg_replace('/(\w+)(?:\s*=\s*(?:[\'"].*?[\'"]|[\d-.]+)|\s+IN\s+\([^\)]+\))/', '$1 = \?', $query);
	}

	/**
	 * @param string $query
	 * @return string
	 */
	private function computeHash(string $query): string
	{
		return md5($query);
	}

}