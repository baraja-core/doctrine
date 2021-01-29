<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Cache;


final class SQLite3Cache extends \Doctrine\Common\Cache\SQLite3Cache
{
	public function __construct(string $cachePath)
	{
		$cache = new \SQLite3($cachePath);
		$cache->enableExceptions(true);
		if ($cache->busyTimeout(60_000) === false) {
			throw new \RuntimeException('SQLite3 cache: Can not set busy timeout: ' . $cache->lastErrorMsg(), $cache->lastErrorCode());
		}
		$cache->exec('PRAGMA journal_mode = wal;');

		parent::__construct($cache, 'doctrine');
	}
}
