<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


final class ConfiguratorException extends DatabaseException
{

	/**
	 * @param string $type
	 * @param string $className
	 */
	public static function typeDoesNotExist(string $type, string $className): void
	{
		throw new self('Doctrine type "' . $type . '" does not exist, because class "' . $className . '" is not defined.');
	}

}
