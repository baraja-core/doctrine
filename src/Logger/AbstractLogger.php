<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\Logger;


use Baraja\Doctrine\Entity\SlowQuery;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Doctrine\Logger\Event;
use Baraja\Doctrine\Utils;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\EntityManagerInterface;
use Tracy\Debugger;
use Tracy\ILogger;

abstract class AbstractLogger implements SQLLogger
{
	/** @var Event[] */
	private array $events = [];

	/** Log an error if this time has been exceeded. */
	private int $maxQueryTime = 150;

	private float $startTime;


	public function __construct(
		private ?EntityManagerInterface $entityManager,
	) {
		if (class_exists(Debugger::class)) {
			$this->startTime = (float) Debugger::$time;
		} else {
			$this->startTime = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
		}
	}


	/**
	 * @param mixed[] $params
	 * @param mixed[] $types
	 */
	public function startQuery($sql, ?array $params = null, ?array $types = null): void
	{
		if (count($this->events) > 300) {
			return;
		}

		$hash = Utils::createSqlHash($sql);
		if (
			isset($_GET['trackingSqlEnabled'])
			&& ($_GET['trackingSqlHash'] ?? '') === $hash
			&& Debugger::$productionMode === false
		) {
			Debugger::log(
				new \RuntimeException(
					'Debug tracking point for query "' . $hash . '".'
					. "\n" . 'SQL: ' . $sql
					. "\n" . 'Params: ' . json_encode($params),
				),
				ILogger::DEBUG,
			);
		}

		$this->events[] = new Event(
			sql: $sql,
			hash: $hash,
			params: $params ?? [],
			types: $types ?? [],
			delayTime: (microtime(true) - $this->startTime) * 1000,
			location: $this->findLocation(),
		);
	}


	public function stopQuery(): ?Event
	{
		static $locked = false;
		$keys = array_keys($this->events);
		$key = end($keys);

		if (isset($this->events[$key]) === false) {
			return null;
		}

		$event = $this->events[$key];

		if (
			$locked === false
			&& $this->entityManager !== null
			&& ($event->getDurationMs() ?? 0) > $this->maxQueryTime
		) {
			$locked = true;
			$hash = Utils::createSqlHash($event->getSql());
			if (Utils::queryExistsByHash($hash, $this->entityManager) === false) {
				try {
					$slowQuery = new SlowQuery($event);
					$this->entityManager->persist($slowQuery);
					$this->entityManager->getUnitOfWork()->commit($slowQuery);
				} catch (EntityManagerException $e) {
					Debugger::log($e, ILogger::DEBUG);
				}
			}
			$locked = false;
		}

		return $event;
	}


	/**
	 * @return Event[]
	 */
	public function getEvents(): array
	{
		return $this->events;
	}


	public function getCounter(): int
	{
		return count($this->events);
	}


	public function getTimer(): float
	{
		$sum = 0;
		foreach ($this->events as $query) {
			$sum += $query->getDuration() ?? 0;
		}

		return (float) $sum;
	}


	/**
	 * Set max query time to log in interval (0 - 30 sec).
	 * Time in milliseconds.
	 */
	public function setMaxQueryTime(int $maxQueryTime): void
	{
		if ($maxQueryTime < 0) {
			$maxQueryTime = 0;
		} elseif ($maxQueryTime > 30_000) {
			$maxQueryTime = 30_000;
		}

		$this->maxQueryTime = $maxQueryTime;
	}


	/**
	 * Finds the location where dump was called.
	 *
	 * @return array{file: string, line: int, snippet: string}|null
	 */
	private function findLocation(): ?array
	{
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $item) {
			if (isset($item['class']) && $item['class'] === self::class) {
				$location = $item;
				continue;
			}
			if (
				preg_match(
					'/\/vendor\/([^\/]+\/[^\/]+)\//',
					str_replace(
						DIRECTORY_SEPARATOR,
						'/',
						$item['file'] ?? ''
					),
					$parser
				)
				&& (
					$parser[1] === 'baraja-core/doctrine'
					|| strncmp($parser[1], 'doctrine/', 9) === 0
				)
			) {
				continue;
			}

			$location = $item;
			break;
		}

		if (isset($location['file'], $location['line']) && is_file($location['file'] ?? '')) {
			/** @phpstan-ignore-next-line */
			$locationLine = file($location['file'] ?? '')[(int) ($location['line'] ?? 0) - 1] ?? 1;

			return [
				'file' => (string) ($location['file'] ?? ''),
				'line' => (int) ($location['line'] ?? 0),
				'snippet' => trim(
					(string) preg_match(
						'#\w*dump(er::\w+)?\(.*\)#i',
						(string) $locationLine,
						$m
					) ? $m[0] ?? '' : (string) $locationLine
				),
			];
		}

		return null;
	}
}