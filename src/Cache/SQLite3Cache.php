<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Cache;


final class SQLite3Cache extends \Doctrine\Common\Cache\SQLite3Cache
{
	public function __construct(string $cachePath)
	{
		$cache = new \SQLite3($cachePath);
		$cache->busyTimeout(5000);
		$cache->exec('PRAGMA journal_mode = wal;');

		parent::__construct($cache, 'doctrine');
	}
}
