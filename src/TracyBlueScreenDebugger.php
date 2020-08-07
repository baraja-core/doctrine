<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\Doctrine\DBAL\Utils\QueryUtils;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\QueryException;
use Tracy\BlueScreen;
use Tracy\Dumper;

final class TracyBlueScreenDebugger
{
	/** @var EntityManager|null */
	private static $entityManager;


	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}


	/**
	 * @param EntityManager $entityManager
	 */
	public static function setEntityManager(EntityManager $entityManager): void
	{
		self::$entityManager = $entityManager;
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
		$tab = null;
		$panel = null;

		if (preg_match('/while executing \'(.+)\' with params (.+):(?:\n\s)+(.+)/', $e->getMessage(), $parser)) {
			$tab = 'Driver error | ' . $parser[3];
			$panel = '<p>SQL:</p><pre class="code"><div>' . str_replace("\n", '', QueryUtils::highlight($parser[1])) . '</div></pre>'
				. '<p>With params:</p>' . Dumper::toHtml(json_decode($parser[2]));
		} elseif (preg_match('/while executing \'(.+)\'/', $e->getMessage(), $parser)) {
			$tab = 'Driver error';
			$panel = '<p>SQL:</p><pre class="code"><div>' . str_replace("\n", '', QueryUtils::highlight($parser[1])) . '</div></pre>';
		}

		if (self::$entityManager !== null && preg_match('/Table\s\\\'([^\\\']+)\\\'\sdoesn\\\'t\sexist/', $e->getMessage(), $parser)) {
			try {
				$tableList = array_map(static function (array $item): string {
					return (string) (array_values($item)[0] ?? '');
				}, self::$entityManager->getConnection()->executeQuery('show tables')->fetchAll());

				$panelMeta = [];
				foreach (self::$entityManager->getMetadataFactory()->getAllMetadata() as $metaData) {
					if ($metaData instanceof ClassMetadata) {
						$panelMeta[$metaData->getTableName()] = [
							'tableName' => $metaData->getTableName(),
							'className' => $metaData->getName(),
						];
					}
				}

				$panel .= '<p>Table list:</p>';
				$panel .= '<table>';
				$panel .= '<tr><th>Table name</th><th>Entity class name</th></tr>';
				$panel .= '<tr>'
					. '<td style="background:#bf0014;color:white"><b>' . htmlspecialchars($parser[1]) . '</b></td>'
					. '<td style="background:#bf0014;color:white">Can not find. Please use command <b>index.php&nbsp;o:s:u&nbsp;-f</b></td>'
					. '</tr>';

				foreach ($tableList as $tableName) {
					$panel .= '<tr>'
						. '<td>' . htmlspecialchars($tableName) . '</td>'
						. '<td>' . htmlspecialchars($panelMeta[$tableName]['className'] ?? '???') . '</td>'
						. '</tr>';
				}

				$panel .= '</table>';
			} catch (DBALException $e) {
			}
		}

		return [
			'tab' => $tab ?? 'Driver error',
			'panel' => $panel ?? '<p>' . htmlspecialchars($e->getMessage()) . '</p>',
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
