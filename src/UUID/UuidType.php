<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class UuidType extends Type
{
	public const NAME = 'uuid';


	/**
	 * @param mixed[] $fieldDeclaration
	 * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
	 * @return string
	 */
	public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
	{
		return $platform->getGuidTypeDeclarationSQL($fieldDeclaration);
	}


	/**
	 * @param string|UuidInterface|mixed|null $value
	 * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
	 * @return string|null
	 * @throws ConversionException
	 */
	public function convertToPHPValue($value, AbstractPlatform $platform): ?string
	{
		if ($value === null) {
			return null;
		}
		if ($value instanceof UuidInterface) {
			return $value->toString();
		}

		try {
			return (string) Uuid::fromString($value)->toString();
		} catch (\InvalidArgumentException $e) {
			throw ConversionException::conversionFailed($value, static::NAME);
		}
	}


	/**
	 * @param UuidInterface|string|mixed|null $value
	 * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
	 * @return string|null
	 * @throws ConversionException
	 */
	public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
	{
		if (empty($value)) {
			return null;
		}
		if ($value instanceof UuidInterface
			|| ((\is_string($value) || method_exists($value, '__toString')) && Uuid::isValid((string) $value))
		) {
			return (string) $value;
		}

		throw ConversionException::conversionFailed($value, static::NAME);
	}


	public function getName(): string
	{
		return static::NAME;
	}


	public function requiresSQLCommentHint(AbstractPlatform $platform): bool
	{
		return true;
	}
}
