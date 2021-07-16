<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Entity;


use Baraja\Doctrine\Logger\Event;
use Baraja\Doctrine\Utils;
use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

#[ORM\Entity]
#[ORM\Table(name: 'core__database_slow_query')]
#[Index(columns: ['id', 'hash'], name: 'database_slow_query__id_hash')]
class SlowQuery
{
	use UuidIdentifier;

	#[ORM\Column(type: 'text')]
	private string $query;

	#[ORM\Column(type: 'float')]
	private float $duration;

	#[ORM\Column(type: 'string', length: 32, unique: true)]
	private string $hash;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;


	public function __construct(Event $event)
	{
		$this->query = trim($event->getSql());
		$this->duration = $event->getDurationMs() ?? 0;
		$this->hash = Utils::createSqlHash($event->getSql());
		$this->insertedDate = new \DateTime('now');
	}


	public function getQuery(): string
	{
		return $this->query;
	}


	public function getDuration(): float
	{
		return $this->duration;
	}


	public function getHash(): string
	{
		return $this->hash;
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}
}
