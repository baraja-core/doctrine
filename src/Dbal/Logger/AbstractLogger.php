<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\Logger;


use Baraja\Doctrine\Entity\SlowQuery;
use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use stdClass;
use Tracy\Debugger;

abstract class AbstractLogger implements SQLLogger
{

	/**
	 * @var mixed[]
	 */
	protected $queries = [];

	/**
	 * @var float
	 */
	protected $totalTime = 0;

	/**
	 * @var EntityManager|null
	 */
	private $entityManager;

	/**
	 * @var float[]
	 */
	private $queriesTimer = [];

	/**
	 * @var int
	 */
	private $counter = 0;

	/**
	 * @var bool[]
	 */
	private $slowQueryHashes = [];

	/**
	 * @param EntityManager $entityManager
	 */
	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	/**
	 * @param mixed $sql
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
		$keys = array_keys($this->queriesTimer);
		$key = end($keys);

		$this->queriesTimer[$key]['end'] = microtime(true);
		$this->queriesTimer[$key]['duration'] = $this->queriesTimer[$key]['end'] - $this->queriesTimer[$key]['start'];
		$this->queriesTimer[$key]['ms'] = $this->queriesTimer[$key]['duration'] * 1000;

		$this->totalTime += $this->queriesTimer[$key]['duration'] * 1000;

		if (isset($this->queries[$key])) {
			$duration = (float) $this->queriesTimer[$key]['duration'];
			$durationMs = $duration * 1000;
			$this->queries[$key]->end = $this->queriesTimer[$key]['end'];
			$this->queries[$key]->duration = $duration;
			$this->queries[$key]->ms = $this->queriesTimer[$key]['ms'];

			if ($this->entityManager !== null && $durationMs > 150) {
				$slowQuery = new SlowQuery($this->queries[$key]->sql, $durationMs);
				if ($this->queryExistsByHash($slowQuery->getHash()) === false) {
					try {
						$this->entityManager->persist($slowQuery)->flush($slowQuery);
					} catch (EntityManagerException $e) {
						Debugger::log($e);
					}

					$this->slowQueryHashes[$slowQuery->getHash()] = true;
				}
			}
		}

		return $this->queries[$key] ?? null;
	}

	/**
	 * @return int
	 */
	public function getCounter(): int
	{
		return $this->counter;
	}

	/**
	 * @return float
	 */
	public function getTimer(): float
	{
		return $this->totalTime;
	}

	/**
	 * Finds the location where dump was called. Returns [file, line, code]
	 */
	private function findLocation(): ?array
	{
		static $exclude = ['baraja/database' => 1, 'baraja/doctrine' => 1];

		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $item) {
			if (isset($item['class']) && $item['class'] === __CLASS__) {
				$location = $item;
				continue;
			}

			if (preg_match('/\/vendor\/([^\/]+\/[^\/]+)\//', $item['file'] ?? '', $parser) && isset($exclude[$parser[1]])) {
				continue;
			}

			$location = $item;
			break;
		}

		if (isset($location['file'], $location['line']) && is_file($location['file'])) {
			$lines = file($location['file']);
			$line = $lines[$location['line'] - 1];

			return [
				'file' => $location['file'],
				'line' => $location['line'],
				'snippet' => trim(preg_match('#\w*dump(er::\w+)?\(.*\)#i', $line, $m) ? $m[0] : $line),
			];
		}

		return null;
	}

	/**
	 * @param string $hash
	 * @return bool
	 */
	private function queryExistsByHash(string $hash): bool
	{
		if (isset($this->slowQueryHashes[$hash]) === true) {
			return true;
		}

		try {
			$this->entityManager->getRepository(SlowQuery::class)
				->createQueryBuilder('slowQuery')
				->where('slowQuery.hash = :hash')
				->setParameter('hash', $hash)
				->getQuery()
				->getSingleResult();

			$this->slowQueryHashes[$hash] = true;
		} catch (NoResultException|NonUniqueResultException $e) {
			return false;
		}

		return true;
	}

}
