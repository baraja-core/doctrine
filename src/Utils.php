<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;

final class Utils
{
	private const DOC_PATTERN = '/[@=](?<name>[^\(\s\n]+)\s*(?<value>[^\n]+)/';


	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}


	/**
	 * Find annotation /^[@=] in class DocComment by reflection.
	 *
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
	 */
	public static function functionIsAvailable(string $functionName): bool
	{
		static $disabled;
		if (\function_exists($functionName) === true) {
			if ($disabled === null && \is_string($disableFunctions = ini_get('disable_functions'))) {
				$disabled = explode(',', $disableFunctions) ?: [];
			}

			return \in_array($functionName, $disabled ?? [], true) === false;
		}

		return false;
	}


	public static function createSqlHash(string $sql): string
	{
		$sql = (string) preg_replace('/\'[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}\'/', '\'uuid\'', $sql);
		$sql = (string) preg_replace('/[^\']+/', '\'string\'', $sql);
		$sql = (string) preg_replace('/(\w+)(?:\s*=\s*(?:[\'"].*?[\'"]|[\d\-\.]+)|\s+IN\s+\([^\)]+\))/', '$1 = \?', $sql);

		return md5($sql);
	}


	/**
	 * Fast check of record existence.
	 */
	public static function queryExistsByHash(string $hash, EntityManagerInterface $em): bool
	{
		static $cache = [];

		if (isset($cache[$hash]) === true) {
			return $cache[$hash];
		}
		try {
			$hashExist = $em->getConnection()
				->executeQuery('SELECT 1 FROM `core__database_slow_query` WHERE hash = \'' . $hash . '\'')
				->fetch();
		} catch (DBALException $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
		}
		if ($hashExist !== false) {
			$cache[$hash] = true;
		}

		return $hashExist !== false;
	}
}
