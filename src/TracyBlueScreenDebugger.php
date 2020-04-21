<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\Doctrine\DBAL\Utils\QueryUtils;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\QueryException;
use Tracy\BlueScreen;
use Tracy\Dumper;

final class TracyBlueScreenDebugger
{
	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}


	/**
	 * @param \Throwable|null $e
	 * @return string[]|null
	 */
	public static function render(?\Throwable $e): ?array
	{
		if ($e instanceof DriverException) {
			return self::renderDriver($e);
		}

		if ($e === null || !$e instanceof ORMException) {
			return null;
		}

		if ($e instanceof QueryException) {
			return self::renderQuery($e);
		}

		if ($e instanceof MappingException) {
			return self::renderMapping($e);
		}

		return self::renderCommon($e);
	}


	/**
	 * @param ORMException $e
	 * @return string[]
	 */
	private static function renderCommon(ORMException $e): array
	{
		return [
			'tab' => 'ORM error',
			'panel' => '<p>' . htmlspecialchars($e->getMessage()) . '</p>',
		];
	}


	/**
	 * @param DriverException $e
	 * @return string[]
	 */
	private static function renderDriver(DriverException $e): array
	{
		if (preg_match('/while executing \'(.+)\' with params (.+):(?:\n\s)+(.+)/', $e->getMessage(), $parser)) {
			return [
				'tab' => 'Driver error | ' . $parser[3],
				'panel' => '<pre class="code"><div>' . QueryUtils::highlight($parser[1]) . '</div></pre>'
					. '<p>With params:</p>' . Dumper::toHtml(json_decode($parser[2])),
			];
		}

		if (preg_match('/while executing \'(.+)\'/', $e->getMessage(), $parser)) {
			return [
				'tab' => 'Driver error',
				'panel' => '<pre class="code"><div>' . QueryUtils::highlight($parser[1]) . '</div></pre>',
			];
		}

		return [
			'tab' => 'Driver error',
			'panel' => '<p>' . htmlspecialchars($e->getMessage()) . '</p>',
		];
	}


	/**
	 * @param QueryException $e
	 * @return string[]
	 */
	private static function renderQuery(QueryException $e): array
	{
		return [
			'tab' => 'Query error',
			'panel' => '<p>' . htmlspecialchars($e->getMessage()) . '</p>',
		];
	}


	/**
	 * @param MappingException $e
	 * @return string[]
	 */
	private static function renderMapping(MappingException $e): array
	{
		if (preg_match('/Class "([^"]+)"/', $e->getMessage(), $parser)) {
			if (class_exists($className = $parser[1]) === true) {
				$fileName = null;
				$fileContent = null;
				$docComment = '';
				$startLine = 1;
				try {
					$fileName = ($ref = new \ReflectionClass($className))->getFileName();
					$fileContent = \is_file($fileName) ? file_get_contents($fileName) : null;
					$startLine = $ref->getStartLine();
					$docComment = trim((string) $ref->getDocComment());
				} catch (\ReflectionException $e) {
				}

				if ($fileName !== null && $fileContent !== null) {
					return [
						'tab' => 'Mapping error',
						'panel' => '<p>Class <b>' . htmlspecialchars($className) . '</b>, path: <b>' . htmlspecialchars($fileName) . '</b></p>'
							. BlueScreen::highlightPhp(htmlspecialchars($fileContent, ENT_IGNORE, 'UTF-8'), $startLine)
							. '<p>Doc comment:</p>'
							. ($docComment === '' ? '<i>Doc comment is empty.</i>' : '<pre>' . htmlspecialchars($docComment) . '</pre>'),
					];
				}
			}

			return [
				'tab' => 'Mapping error',
				'panel' => '<p>Class "' . htmlspecialchars($className) . '" does not exist!</p>'
					. '<p>' . htmlspecialchars($e->getMessage()) . '</p>',
			];
		}

		return [
			'tab' => 'Mapping error',
			'panel' => '<p>' . htmlspecialchars($e->getMessage()) . '</p>',
		];
	}
}
