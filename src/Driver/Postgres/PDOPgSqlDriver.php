<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Driver\Postgres;


use Doctrine\DBAL\Driver\PDOPgSql\Driver as ParentDriver;

class PDOPgSqlDriver extends ParentDriver
{
	public function createDatabasePlatformForVersion($version)
	{
		return new PostgreSQL100Platform;
	}


	public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
	{
		return new Postgre10SqlSchemaManager($conn);
	}
}
