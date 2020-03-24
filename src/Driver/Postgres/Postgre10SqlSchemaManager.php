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
			if (\is_array($sequence) === true) {
				$sequence = array_merge($sequence, $this->_conn->fetchAssoc('SELECT min_value, increment_by FROM ' . $this->_platform->quoteIdentifier($sequenceName)));
			} else {
				throw new \RuntimeException('Sequence must be type of array, type "' . \gettype($sequence) . '" given.');
			}
		}

		return new Sequence($sequenceName, $sequence['increment_by'], $sequence['min_value']);
	}
}