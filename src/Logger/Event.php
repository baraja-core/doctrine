<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Logger;


final class Event
{
	private float $start;

	private ?float $end = null;

	private ?float $duration = null;


	/**
	 * @param mixed[] $params
	 * @param mixed[] $types
	 * @param array{file: string, line: int, snippet: string}|null $location
	 */
	public function __construct(
		private string $sql,
		private string $hash,
		private array $params,
		private array $types,
		private float $delayTime,
		private ?array $location,
	) {
		$this->start = (float) microtime(true);
	}


	public function end(): void
	{
		if ($this->end !== null) {
			return;
		}
		$end = (float) microtime(true);
		$this->end = $end;
		$this->duration = $end - $this->start;
	}


	public function getSql(): string
	{
		return $this->sql;
	}


	public function getHash(): string
	{
		return $this->hash;
	}


	/**
	 * @return mixed[]
	 */
	public function getParams(): array
	{
		return $this->params;
	}


	/**
	 * @return mixed[]
	 */
	public function getTypes(): array
	{
		return $this->types;
	}


	public function getDelayTime(): float
	{
		return $this->delayTime;
	}


	/**
	 * @return array{file: string, line: int, snippet: string}|null
	 */
	public function getLocation(): ?array
	{
		return $this->location;
	}


	public function getStart(): float
	{
		return $this->start;
	}


	public function getEnd(): ?float
	{
		$this->end();

		return $this->end;
	}


	public function getDuration(): ?float
	{
		$this->end();

		return $this->duration;
	}


	public function getDurationMs(): ?float
	{
		$this->end();

		return $this->duration
			? $this->duration * 1_000
			: null;
	}
}
