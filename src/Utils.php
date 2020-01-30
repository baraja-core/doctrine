<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


final class Utils
{

	private const DOC_PATTERN = '/[@=](?<name>[^\(\s\n]+)\s*(?<value>[^\n]+)/';

	/**
	 * @throws \Error
	 */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}

	/**
	 * Find annotation /^[@=] in class DocComment by reflection.
	 *
	 * @param string $class
	 * @param string $key
	 * @return string|null
	 * @throws \ReflectionException
	 */
	public static function reflectionClassDocComment(string $class, string $key): ?string
	{
		if (preg_match_all(self::DOC_PATTERN, (string) self::getReflectionClass($class)->getDocComment(), $matches)) {
			foreach ($matches['name'] ?? [] as $matchKey => $match) {
				if (strtolower($match) === strtolower($key)) {
					return trim($matches['value'][$matchKey]);
				}
			}
		}

		return null;
	}

	/**
	 * Return reflection class by given class name. In case of repeated use return reflection by cache.
	 *
	 * @param string $class
	 * @return \ReflectionClass
	 * @throws \ReflectionException
	 */
	public static function getReflectionClass(string $class): \ReflectionClass
	{
		static $cache = [];

		if (isset($cache[$class]) === false) {
			$cache[$class] = new \ReflectionClass($class);
		}

		return $cache[$class];
	}

	/**
	 * Safe detection if function is available to call.
	 *
	 * @param string $functionName
	 * @return bool
	 */
	public static function functionIsAvailable(string $functionName): bool
	{
		static $disabled;

		if (\function_exists($functionName) === true) {
			if ($disabled === null && \is_string($disableFunctions = ini_get('disable_functions'))) {
				$disabled = explode(',', $disableFunctions) ? : [];
			}

			return \in_array($functionName, $disabled ?? [], true) === false;
		}

		return false;
	}

}
