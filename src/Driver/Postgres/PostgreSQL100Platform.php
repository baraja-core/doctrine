<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Driver\Postgres;


use Doctrine\DBAL\Platforms\Keywords\PostgreSQL94Keywords;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;

class PostgreSQL100Platform extends PostgreSQL94Platform
{
	public function getListSequencesSQL($database): string
	{
		return (string) str_replace(
			'%var%',
			$this->quoteStringLiteral($database),
			<<<'SQL'
				SELECT sequence_name AS relname,
					   sequence_schema AS schemaname,
					   minimum_value AS min_value,
					   increment AS increment_by
				FROM   information_schema.sequences
				WHERE  sequence_catalog = %var%
				AND    sequence_schema NOT LIKE 'pg_%'
				AND    sequence_schema != 'information_schema'
				SQL,
		);
	}


	protected function getReservedKeywordsClass(): string
	{
		return PostgreSQL94Keywords::class;
	}
}
