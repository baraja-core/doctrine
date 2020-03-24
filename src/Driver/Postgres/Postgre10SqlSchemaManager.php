<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Driver\Postgres;


use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;
use Doctrine\DBAL\Schema\Sequence;

class Postgre10SqlSchemaManager extends PostgreSqlSchemaManager
{

	/**
	 * {@inheritdoc}
	 */
	protected function _getPortableSequenceDefinition($sequence)
	{
		if ($sequence['schemaname'] !== 'public') {
			$sequenceName = $sequence['schemaname'] . '.' . $sequence['relname'];
		} else {
			$sequenceName = $sequence['relname'];
		}

		if (isset($sequence['increment_by'], $sequence['min_value']) === false) {
			$sequence = array_merge($sequence, $this->_conn->fetchAssoc('SELECT min_value, increment_by FROM ' . $this->_platform->quoteIdentifier($sequenceName)));
		}

		return new Sequence($sequenceName, $sequence['increment_by'], $sequence['min_value']);
	}
}