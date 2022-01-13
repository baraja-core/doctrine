<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class UuidBinaryType extends Type
{
	public const NAME = 'uuid-binary';


	/**
	 * @param mixed[] $fieldDeclaration
	 */
	public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
	{
		return $platform->getBinaryTypeDeclarationSQL([
			'length' => '16',
			'fixed' => true,
		]);
	}


	/**
	 * @throws ConversionException
	 */
	public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UuidInterface
	{
		if ($value === null || $value === '') {
			return null;
		}
		if ($value instanceof UuidInterface) {
			return $value;
		}
		if (is_string($value)) {
			try {
				return Uuid::fromBytes($value);
			} catch (\InvalidArgumentException) {
				throw ConversionException::conversionFailed($value, static::NAME);
			}
		}

		return null;
	}


	/**
	 * @param UuidInterface|string|mixed|null $value
	 * @throws ConversionException
	 */
	public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
	{
		if ($value === null) {
			return null;
		}
		if ($value instanceof UuidInterface) {
			return $value->getBytes();
		}
		try {
			$string = is_string($value) || method_exists($value, '__toString')
				? (string) $value
				: '';
			if ($string !== '') {
				return Uuid::fromString($string)->getBytes();
			}
		} catch (\InvalidArgumentException) {
			// Ignore the exception and pass through.
		}

		throw ConversionException::conversionFailed((string) $value, static::NAME);
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
