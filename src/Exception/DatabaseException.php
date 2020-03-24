<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


class DatabaseException extends \RuntimeException
{

	/**
	 * @param \Throwable $e
	 * @throws DatabaseException
	 */
	public static function e(\Throwable $e): void
	{
		throw new self($e->getMessage(), $e->getCode(), $e);
	}


	/**
	 * @param string|null $id
	 * @throws DatabaseException
	 */
	public static function canNotSetIdentifier(?string $id): void
	{
		throw new self('Can not set identifier "' . $id . '", please use trait UuidIdentifier.');
	}


	/**
	 * @param string $fromTable
	 * @param string $to
	 * @throws DatabaseException
	 */
	public static function remapDifferentTypes(string $fromTable, string $to): void
	{
		throw new self('Entities for remap must be same table type, "' . $fromTable . '" and "' . $to . '" given.');
	}


	/**
	 * @param string $className
	 * @throws DatabaseException
	 */
	public static function entityMustImplement(string $className): void
	{
		throw new self('Entity "' . $className . '" must implement getParent(), setParent() and getPosition().');
	}
}