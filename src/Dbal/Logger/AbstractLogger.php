<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\Logger;


use Baraja\Doctrine\Entity\SlowQuery;
use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Doctrine\Utils;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use stdClass;
use Tracy\Debugger;
use Tracy\ILogger;

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
		$this->queriesTimer[$key = end($keys)]['end'] = microtime(true);
		$this->queriesTimer[$key]['duration'] = $this->queriesTimer[$key]['end'] - $this->queriesTimer[$key]['start'];
		$this->queriesTimer[$key]['ms'] = $this->queriesTimer[$key]['duration'] * 1000;
		$this->totalTime += $this->queriesTimer[$key]['duration'] * 1000;

		if (isset($this->queries[$key]) === true) {
			$this->queries[$key]->end = $this->queriesTimer[$key]['end'];
			$this->queries[$key]->duration = ($duration = (float) $this->queriesTimer[$key]['duration']);
			$this->queries[$key]->ms = $this->queriesTimer[$key]['ms'];

			if ($this->entityManager !== null && ($durationMs = $duration * 1000) > 150
				&& $this->queryExistsByHash($hash = Utils::createSqlHash($this->queries[$key]->sql)) === false
			) {
				try {
					$this->entityManager->persist($slowQuery = new SlowQuery($this->queries[$key]->sql, $hash, $durationMs))->flush($slowQuery);
				} catch (EntityManagerException $e) {
					Debugger::log($e, ILogger::DEBUG);
				}

				$this->slowQueryHashes[$hash] = true;
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
		static $exclude = ['baraja-core/doctrine' => 1, 'janbarasek/doctrine-pro' => 1];

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
			return [
				'file' => $location['file'],
				'line' => $location['line'],
				'snippet' => trim(preg_match('#\w*dump(er::\w+)?\(.*\)#i', $line = file($location['file'])[$location['line'] - 1], $m) ? $m[0] : $line),
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
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();

			$this->slowQueryHashes[$hash] = true;
		} catch (NoResultException|NonUniqueResultException $e) {
			return false;
		}

		return true;
	}

}
