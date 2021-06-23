<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Cache;


use function array_search;
use Doctrine\Common\Cache\CacheProvider;
use function implode;
use function serialize;
use function sprintf;
use function time;
use function unserialize;

final class SQLite3Cache extends CacheProvider
{
	/** The ID field will store the cache key. */
	public const ID_FIELD = 'k';

	/** The data field will store the serialized PHP value. */
	public const DATA_FIELD = 'd';

	/** The expiration field will store a date value indicating when the cache entry should expire. */
	public const EXPIRATION_FIELD = 'e';

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
				if ($ttl > 0 && str_contains($e->getMessage(), 'database is locked')) {
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
		if (!$item) {
			return false;
		}

		return unserialize($item[self::DATA_FIELD]);
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
				implode(',', $this->getFields())
			)
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
				$idField
			)
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
				static::ID_FIELD,
				static::DATA_FIELD,
				static::EXPIRATION_FIELD
			)
		);
	}


	/**
	 * Find a single row by ID.
	 *
	 * @return mixed[]|null
	 */
	private function findById(mixed $id, bool $includeData = true): ?array
	{
		[$idField] = $fields = $this->getFields();

		if (!$includeData) {
			$key = array_search(static::DATA_FIELD, $fields, true);
			unset($fields[$key]);
		}

		$statement = $this->sqlite->prepare(
			sprintf(
				'SELECT %s FROM %s WHERE %s = :id LIMIT 1',
				implode(',', $fields),
				$this->table,
				$idField
			)
		);
		assert($statement !== false);

		$statement->bindValue(':id', $id, SQLITE3_TEXT);

		$result = $statement->execute();
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
	 * @phpstan-return array{string, string, string}
	 */
	private function getFields(): array
	{
		return [self::ID_FIELD, self::DATA_FIELD, self::EXPIRATION_FIELD];
	}


	/**
	 * Check if the item is expired.
	 *
	 * @param mixed[] $item
	 */
	private function isExpired(array $item): bool
	{
		return isset($item[self::EXPIRATION_FIELD])
			&& $item[self::EXPIRATION_FIELD] !== null
			&& $item[self::EXPIRATION_FIELD] < time();
	}
}
