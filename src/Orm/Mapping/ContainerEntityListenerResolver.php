<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM\Mapping;


use Doctrine\ORM\Mapping\EntityListenerResolver;
use InvalidArgumentException;
use Nette\DI\Container;

final class ContainerEntityListenerResolver implements EntityListenerResolver
{
	/** @var array<string, object> */
	private array $instances = [];


	public function __construct(
		private Container $container,
	) {
	}


	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param string|null $className
	 */
	public function clear($className = null): void
	{
		if ($className === null) {
			$this->instances = [];

			return;
		}

		$className = trim($className, '\\');
		if (isset($this->instances[$className])) {
			unset($this->instances[$className]);
		}
	}


	/**
	 * @param object|mixed $object
	 */
	public function register($object): void
	{
		if (!is_object($object)) {
			throw new InvalidArgumentException(sprintf('An object was expected, but got "%s".', gettype($object)));
		}

		$this->instances[$object::class] = $object;
	}


	/**
	 * @param string $className
	 * @return object
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint
	 */
	public function resolve($className)
	{
		$className = trim($className, '\\');
		assert(class_exists($className));
		if (isset($this->instances[$className])) {
			return $this->instances[$className];
		}

		if ($this->container->getByType($className, false)) {
			$this->instances[$className] = $this->container->getByType($className);
		} else {
			$this->instances[$className] = new $className;
		}

		return $this->instances[$className];
	}
}
