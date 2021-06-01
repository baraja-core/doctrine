<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


class DatabaseException extends \RuntimeException
{
	/**
	 * @deprecated since 2021-06-01 method will be removed in next master version.
	 * @throws DatabaseException
	 */
	public static function e(\Throwable $e): void
	{
		throw new self($e->getMessage(), $e->getCode(), $e);
	}


	/**
	 * @deprecated since 2021-06-01 method will be removed in next master version.
	 * @throws DatabaseException
	 */
	public static function canNotSetIdentifier(?string $id): void
	{
		throw new self('Can not set identifier "' . $id . '", please use trait UuidIdentifier.');
	}


	/**
	 * @deprecated since 2021-06-01 method will be removed in next master version.
	 * @throws DatabaseException
	 */
	public static function remapDifferentTypes(string $fromTable, string $to): void
	{
		throw new self('Entities for remap must be same table type, "' . $fromTable . '" and "' . $to . '" given.');
	}
}
