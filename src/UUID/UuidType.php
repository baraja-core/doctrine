<?php

declare(strict_types=1);

namespace Baraja\Doctrine\UUID;


use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class UuidType extends Type
{
	public const NAME = 'uuid';


	/**
	 * @param mixed[] $fieldDeclaration
	 */
	public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
	{
		return $platform->getGuidTypeDeclarationSQL($fieldDeclaration);
	}


	/**
	 * @param string|UuidInterface|mixed|null $value
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
			return Uuid::fromString($value)->toString();
		} catch (\InvalidArgumentException) {
			throw ConversionException::conversionFailed($value, static::NAME);
		}
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
		if (\is_string($value)) {
			$return = $value;
		} elseif ($value instanceof UuidInterface) {
			$return = $value->toString();
		} elseif (\is_object($value) && method_exists($value, '__toString')) {
			$return = (string) $value;
		} else {
			throw new \InvalidArgumentException(
				'Value must be string or instance of "' . UuidInterface::class . '", '
				. 'but type "' . \gettype($value) . '" given.',
			);
		}
		if (Uuid::isValid($return)) {
			return $return;
		}

		throw ConversionException::conversionFailed($return, static::NAME);
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
