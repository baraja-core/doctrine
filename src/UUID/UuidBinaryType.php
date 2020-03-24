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

	/** @var string */
	public const NAME = 'uuid-binary';


	/**
	 * @param mixed[] $fieldDeclaration
	 * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
	 * @return string
	 */
	public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
	{
		return $platform->getBinaryTypeDeclarationSQL([
			'length' => '16',
			'fixed' => true,
		]);
	}


	/**
	 * @param string|UuidInterface|null $value
	 * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
	 * @return UuidInterface|null
	 * @throws ConversionException
	 */
	public function convertToPHPValue($value, AbstractPlatform $platform): ?UuidInterface
	{
		if (empty($value)) {
			return null;
		}

		if ($value instanceof UuidInterface) {
			return $value;
		}

		try {
			return Uuid::fromBytes($value);
		} catch (\InvalidArgumentException $e) {
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

		if ($value instanceof UuidInterface) {
			return $value->getBytes();
		}

		try {
			if (is_string($value) || method_exists($value, '__toString')) {
				return Uuid::fromString((string) $value)->getBytes();
			}
		} catch (\InvalidArgumentException $e) {
			// Ignore the exception and pass through.
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