<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\Utils;


use Doctrine\DBAL\Query\QueryBuilder;
use Nette\Utils\Paginator;

final class OracleUtils
{

	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}


	public static function limitSql(QueryBuilder $builder, Paginator $paginator): string
	{
		$sql = $builder->getSQL();

		$sql1 = sprintf(
			'SELECT a.*, ROWNUM AS doctrine_rownum FROM (%s) a',
			$sql
		);

		$sql2 = sprintf(
			'SELECT * FROM (%s) WHERE doctrine_rownum BETWEEN %d AND %d',
			$sql1,
			$paginator->offset,
			$paginator->offset + $paginator->length
		);

		return $sql2;
	}
}
