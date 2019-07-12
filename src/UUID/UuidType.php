<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


use Doctrine\Common\Proxy\Exception\InvalidArgumentException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class UuidType extends Type
{

	/**
	 * @var string
	 */
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
	 * @param string|UuidInterface|null $value
	 * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
	 * @return string|null
	 * @throws ConversionException
	 */
	public function convertToPHPValue($value, AbstractPlatform $platform): ?string
	{
		if (empty($value)) {
			return null;
		}

		if ($value instanceof UuidInterface) {
			return $value;
		}

		try {
			return Uuid::fromString($value)->toString();
		} catch (InvalidArgumentException $e) {
			throw ConversionException::conversionFailed($value, static::NAME);
		}
	}

	/**
	 * @param UuidInterface|string|null $value
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
			|| (
				(\is_string($value) || method_exists($value, '__toString'))
				&& Uuid::isValid((string) $value)
			)
		) {
			return (string) $value;
		}

		throw ConversionException::conversionFailed($value, static::NAME);
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return static::NAME;
	}

	/**
	 * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
	 * @return bool
	 */
	public function requiresSQLCommentHint(AbstractPlatform $platform): bool
	{
		return true;
	}

}