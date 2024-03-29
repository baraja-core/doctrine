<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Cache;


use Doctrine\Common\Cache\CacheProvider;
use Tracy\Debugger;
use Tracy\ILogger;
use function array_search;
use function implode;
use function serialize;
use function sprintf;
use function time;
use function unserialize;

final class SQLite3Cache extends CacheProvider
{
	/** The ID field will store the cache key. */
	public const
		FieldId = 'k', // The ID field will store the cache key.
		FieldData = 'd', // The data field will store the serialized PHP value.
		FieldExpiration = 'e'; // The expiration field will store a date value indicating when the cache entry should expire.

	private \SQLite3 $sqlite;

	private string $table;


	/**
	 * Calling the constructor will ensure that the database file and table
	 * exist and will create both if they don't.
	 */
	public function __construct(string $cachePath, int $ttl = 25, ?string $table = null)
	{
		$cache = new \SQLite3($cachePath);
		$cache->enableExceptions(true);
		if ($cache->busyTimeout(60_000) === false) {
			throw new \RuntimeException(
				'SQLite3 cache: Can not set busy timeout: ' . $cache->lastErrorMsg(),
				$cache->lastErrorCode(),
			);
		}
		do {
			try {
				$cache->exec('PRAGMA journal_mode = wal;');
				break;
			} catch (\Throwable $e) {
				$message = $e->getMessage();
				if (!str_contains($message, 'database is locked') && !str_contains($message, 'disk I/O error')) {
					if (class_exists(Debugger::class)) {
						Debugger::log($e, ILogger::CRITICAL);
						$ttl = 0;
					} else {
						throw $e;
					}
				}
				if ($ttl > 0) {
					usleep(100_000); // 100 ms
					$ttl--;
				} else {
					throw new \RuntimeException('TTL expired: ' . $e->getMessage(), $e->getCode(), $e);
				}
			}
		} while (true);

		$this->sqlite = $cache;
		$this->table = $table ?? 'doctrine';
		$this->ensureTableExists();
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doFetch($id)
	{
		$item = $this->findById($id);
		if ($item === null) {
			return false;
		}

		return unserialize($item[self::FieldData]);
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doContains($id)
	{
		return $this->findById($id, false) !== null;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doSave($id, $data, $lifeTime = 0)
	{
		$statement = $this->sqlite->prepare(
			sprintf(
				'INSERT OR REPLACE INTO %s (%s) VALUES (:id, :data, :expire)',
				$this->table,
				implode(',', $this->getFields()),
			),
		);
		assert($statement !== false);

		$statement->bindValue(':id', $id);
		$statement->bindValue(':data', serialize($data), SQLITE3_BLOB);
		$statement->bindValue(':expire', $lifeTime > 0 ? time() + $lifeTime : null);

		return $statement->execute() instanceof \SQLite3Result;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doDelete($id)
	{
		[$idField] = $this->getFields();

		$statement = $this->sqlite->prepare(
			sprintf(
				'DELETE FROM %s WHERE %s = :id',
				$this->table,
				$idField,
			),
		);
		assert($statement !== false);

		$statement->bindValue(':id', $id);

		return $statement->execute() instanceof \SQLite3Result;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doFlush()
	{
		return $this->sqlite->exec(sprintf('DELETE FROM %s', $this->table));
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doGetStats(): ?array
	{
		return null;
	}


	private function ensureTableExists(): void
	{
		$this->sqlite->exec(
			sprintf(
				'CREATE TABLE IF NOT EXISTS %s(%s TEXT PRIMARY KEY NOT NULL, %s BLOB, %s INTEGER)',
				$this->table,
				self::FieldId,
				self::FieldData,
				self::FieldExpiration,
			),
		);
	}


	/**
	 * Find a single row by ID.
	 *
	 * @return array{k: string, d: string, e: int}|null
	 */
	private function findById(string $id, bool $includeData = true): ?array
	{
		$fields = $this->getFields();
		[$idField] = $fields;

		if (!$includeData) {
			$key = array_search(self::FieldData, $fields, true);
			unset($fields[$key]);
		}

		$statement = $this->sqlite->prepare(
			sprintf(
				'SELECT %s FROM %s WHERE %s = :id LIMIT 1',
				implode(',', $fields),
				$this->table,
				$idField,
			),
		);
		assert($statement !== false);

		$statement->bindValue(':id', $id, SQLITE3_TEXT);

		$result = $statement->execute();

		/** @var array{k: string, d: string, e: int}|false $item */
		$item = $result === false ? false : $result->fetchArray(SQLITE3_ASSOC);

		if ($item === false) {
			return null;
		}
		if ($this->isExpired($item)) {
			$this->doDelete($id);

			return null;
		}

		return $item;
	}


	/**
	 * Gets an array of the fields in our table.
	 *
	 * @return array{string, string, string}
	 */
	private function getFields(): array
	{
		return [self::FieldId, self::FieldData, self::FieldExpiration];
	}


	/**
	 * Check if the item is expired.
	 *
	 * @param array{e?: int} $item
	 */
	private function isExpired(array $item): bool
	{
		return isset($item[self::FieldExpiration]) && $item[self::FieldExpiration] < time();
	}
}
