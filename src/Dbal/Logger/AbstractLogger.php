<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\Logger;


use Baraja\Doctrine\Entity\SlowQuery;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Doctrine\Utils;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\EntityManagerInterface;
use stdClass;
use Tracy\Debugger;
use Tracy\ILogger;

abstract class AbstractLogger implements SQLLogger
{
	/** @var mixed[] */
	protected array $queries = [];

	protected float $totalTime = 0;

	/** @var float[][] */
	private array $queriesTimer = [];

	private int $counter = 0;

	/** Log an error if this time has been exceeded. */
	private int $maxQueryTime = 150;


	public function __construct(
		private ?EntityManagerInterface $entityManager,
	) {
	}


	/**
	 * @param mixed[] $params
	 * @param mixed[] $types
	 */
	public function startQuery($sql, ?array $params = null, ?array $types = null): void
	{
		$this->counter++;
		$this->queriesTimer[] = [
			'start' => microtime(true),
		];

		if ($this->counter > 300) {
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

		$this->queries[] = (object) [
			'sql' => $sql,
			'hash' => $hash,
			'params' => $params,
			'types' => $types,
			'location' => $this->findLocation(),
		];
	}


	public function stopQuery(): ?stdClass
	{
		static $locked = false;
		$keys = array_keys($this->queriesTimer);
		$this->queriesTimer[$key = end($keys)]['end'] = microtime(true);
		$this->queriesTimer[$key]['duration'] = $this->queriesTimer[$key]['end'] - $this->queriesTimer[$key]['start'];
		$this->queriesTimer[$key]['ms'] = $this->queriesTimer[$key]['duration'] * 1_000;
		$this->totalTime += $this->queriesTimer[$key]['duration'] * 1_000;

		if (isset($this->queries[$key]) === true) {
			$this->queries[$key]->end = $this->queriesTimer[$key]['end'];
			$this->queries[$key]->duration = ($duration = (float) $this->queriesTimer[$key]['duration']);
			$this->queries[$key]->ms = $this->queriesTimer[$key]['ms'];

			if (
				$locked === false
				&& $this->entityManager !== null
				&& ($durationMs = $duration * 1_000) > $this->maxQueryTime
			) {
				$locked = true;
				$hash = Utils::createSqlHash($this->queries[$key]->sql);
				if (Utils::queryExistsByHash($hash, $this->entityManager) === false) {
					try {
						$slowQuery = new SlowQuery($this->queries[$key]->sql, $hash, $durationMs);
						$this->entityManager->persist($slowQuery);
						$this->entityManager->getUnitOfWork()->commit($slowQuery);
					} catch (EntityManagerException $e) {
						Debugger::log($e, ILogger::DEBUG);
					}
				}
				$locked = false;
			}
		}

		return $this->queries[$key] ?? null;
	}


	public function getCounter(): int
	{
		return $this->counter;
	}


	public function getTimer(): float
	{
		return (float) $this->totalTime;
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
