<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\Doctrine\Entity\SlowQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Tracy\Debugger;

final class Utils
{
	private const DOC_PATTERN = '/[@=](?<name>[^\(\s\n]+)\s*(?<value>[^\n]+)/';


	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . static::class . ' is static and cannot be instantiated.');
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
			if (\class_exists($class) === false) {
				throw new \RuntimeException('Class "' . $class . '" does not exist.');
			}
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
				$disabled = explode(',', (string) $disableFunctions);
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
			if (($em->getConnection()->getParams()['driver'] ?? '') === 'pdo_mysql') { // fast native query for MySql
				$hashExist = $em->getConnection()
					->executeQuery('SELECT 1 FROM `core__database_slow_query` WHERE `hash` = \'' . $hash . '\'')
					->fetch();
			} else {
				try {
					(new Repository($em, $em->getClassMetadata(SlowQuery::class)))
						->createQueryBuilder('s')
						->where('s.hash = :hash')
						->setParameter('hash', $hash)
						->setMaxResults(1)
						->getQuery()
						->getSingleResult();
					$hashExist = true;
				} catch (NoResultException | NonUniqueResultException) {
					$hashExist = false;
				}
			}
		} catch (\Throwable $e) {
			$hashExist = false;
			if (class_exists(Debugger::class)) {
				Debugger::log($e);
			}
		}
		if ($hashExist !== false) {
			$cache[$hash] = true;
		}

		return $hashExist !== false;
	}
}
