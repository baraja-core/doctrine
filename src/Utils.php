<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Nette\Utils\ArrayHash;

final class Utils
{

	public const DOC_PATTERN = '/[@=](?<name>[^\(\s\n]+)\s*(?<value>[^\n]+)/';

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
		$reflection = self::getReflectionClass($class);
		$key = strtolower($key);

		try {
			foreach (self::matchAll(self::DOC_PATTERN, (string) $reflection->getDocComment()) as $annotation) {
				if (strtolower((string) $annotation->name) === $key) {
					return trim((string) $annotation->value);
				}
			}
		} catch (DatabaseException $e) {
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

		if (!isset($cache[$class])) {
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

		if (\function_exists($functionName)) {
			if ($disabled === null) {
				$disableFunctions = ini_get('disable_functions');

				if (\is_string($disableFunctions)) {
					$disabled = explode(',', $disableFunctions) ? : [];
				}
			}

			return \in_array($functionName, $disabled, true) === false;
		}

		return false;
	}

	/**
	 * Simple way how to match all data by regular expression and return as ArrayHash[].
	 *
	 * @param string $pattern
	 * @param string $data
	 * @param string|null $config
	 * @return ArrayHash[]
	 * @throws DatabaseException
	 */
	private static function matchAll(string $pattern, string $data, ?string $config = null): array
	{
		if (@preg_match_all('/' . trim($pattern, '/') . '/' . ($config ?? ''), $data, $output)) {
			$matches = [];

			for ($i = 0; isset($output[0][$i]); $i++) {
				foreach ($output as $key => $value) {
					foreach ($value as $_key => $_value) {
						$matches[$_key][$key] = $_value;
					}
				}
			}

			$return = [];
			foreach ($matches as $value) {
				$match = new ArrayHash;
				$haystack = new ArrayHash;

				foreach ($value as $_key => $_value) {
					if (\is_string($_key)) {
						$match->{$_key} = $_value;
					} elseif ($_key > 0) {
						$haystack->{$_key} = $_value;
					}
				}

				$match->original = $value[0];
				$match->haystack = $haystack;

				$return[] = $match;
			}

			return $return;
		}

		if (preg_match('/^preg_match_all\(\):?\s+(?<message>.+)$/', error_get_last()['message'], $error)) {
			$errorMessage = trim($error['message']);
			throw new DatabaseException('Invalid regular expression!'
				. ($errorMessage ? ' Hint: ' . $errorMessage : '')
			);
		}

		throw new DatabaseException('Regular not match.');
	}

}
