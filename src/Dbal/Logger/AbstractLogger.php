<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\Logger;


use Baraja\Doctrine\Entity\SlowQuery;
use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Doctrine\Utils;
use Doctrine\DBAL\Logging\SQLLogger;
use stdClass;
use Tracy\Debugger;
use Tracy\ILogger;

abstract class AbstractLogger implements SQLLogger
{

	/** @var mixed[] */
	protected array $queries = [];

	protected float $totalTime = 0;

	private ?EntityManager $entityManager;

	/** @var float[][] */
	private array $queriesTimer = [];

	private int $counter = 0;

	/** Log an error if this time has been exceeded. */
	private int $maxQueryTime = 150;


	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
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

		$this->queries[] = (object) [
			'sql' => $sql,
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
		$this->queriesTimer[$key]['ms'] = $this->queriesTimer[$key]['duration'] * 1000;
		$this->totalTime += $this->queriesTimer[$key]['duration'] * 1000;

		if (isset($this->queries[$key]) === true) {
			$this->queries[$key]->end = $this->queriesTimer[$key]['end'];
			$this->queries[$key]->duration = ($duration = (float) $this->queriesTimer[$key]['duration']);
			$this->queries[$key]->ms = $this->queriesTimer[$key]['ms'];

			if ($locked === false && $this->entityManager !== null && ($durationMs = $duration * 1000) > $this->maxQueryTime) {
				$locked = true;
				if (Utils::queryExistsByHash($hash = Utils::createSqlHash($this->queries[$key]->sql), $this->entityManager) === false) {
					try {
						$this->entityManager->persist($slowQuery = new SlowQuery($this->queries[$key]->sql, $hash, $durationMs))->flush($slowQuery);
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
		} elseif ($maxQueryTime > 30000) {
			$maxQueryTime = 30000;
		}

		$this->maxQueryTime = $maxQueryTime;
	}


	/**
	 * Finds the location where dump was called. Returns [file, line, code]
	 *
	 * @return string[]|null
	 */
	private function findLocation(): ?array
	{
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $item) {
			if (isset($item['class']) && $item['class'] === __CLASS__) {
				$location = $item;
				continue;
			}
			if (preg_match('/\/vendor\/([^\/]+\/[^\/]+)\//', $item['file'] ?? '', $parser) && ($parser[1] === 'baraja-core/doctrine' || strncmp($parser[1], 'doctrine/', 9) === 0)) {
				continue;
			}

			$location = $item;
			break;
		}

		if (isset($location['file'], $location['line']) && is_file($location['file'] ?? '')) {
			return [
				'file' => $location['file'] ?? '',
				'line' => $location['line'] ?? '',
				'snippet' => trim(preg_match('#\w*dump(er::\w+)?\(.*\)#i', $line = file($location['file'] ?? '')[$location['line'] ?? 0 - 1], $m) ? $m[0] : $line),
			];
		}

		return null;
	}
}
