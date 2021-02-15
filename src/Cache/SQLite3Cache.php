<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Cache;


final class SQLite3Cache extends \Doctrine\Common\Cache\SQLite3Cache
{
	public function __construct(string $cachePath, int $ttl = 25)
	{
		$cache = new \SQLite3($cachePath);
		$cache->enableExceptions(true);
		if ($cache->busyTimeout(60_000) === false) {
			throw new \RuntimeException('SQLite3 cache: Can not set busy timeout: ' . $cache->lastErrorMsg(), $cache->lastErrorCode());
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

		parent::__construct($cache, 'doctrine');
	}
}
