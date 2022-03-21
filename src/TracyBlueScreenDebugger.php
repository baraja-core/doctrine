<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\Doctrine\DBAL\Tracy\QueryPanel\QueryPanel;
use Baraja\Doctrine\DBAL\Utils\QueryUtils;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Query\QueryException;
use Tracy\BlueScreen;
use Tracy\Dumper;
use Tracy\Helpers;

final class TracyBlueScreenDebugger
{
	private static ?EntityManager $entityManager = null;

	private static ?QueryPanel $panel = null;


	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . static::class . ' is static and cannot be instantiated.');
	}


	public static function setEntityManager(EntityManager $entityManager): void
	{
		self::$entityManager = $entityManager;
	}


	public static function setPanel(?QueryPanel $panel): void
	{
		self::$panel = $panel;
	}


	/**
	 * @return array{tab: string, panel: string}|null
	 */
	public static function render(?\Throwable $e): ?array
	{
		if (!$e instanceof ORMException) {
			return null;
		}
		if ($e instanceof DriverException) {
			[$tab, $content] = self::renderDriver($e);
		} elseif ($e instanceof QueryException) {
			$tab = 'Query error';
			$content = self::renderQuery($e);
		} elseif ($e instanceof MappingException) {
			$tab = 'MappingException';
			$content = self::renderMapping($e);
		} else {
			$tab = 'ORM error';
			$content = '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
		}

		return [
			'tab' => $tab,
			'panel' => sprintf('
				<div class="tracy-tabs">
					<ul class="tracy-tab-bar">
						<li class="tracy-tab-label tracy-active"><a href="#">%s</a></li>
						<li class="tracy-tab-label"><a href="#">Queries</a></li>
						<li class="tracy-tab-label"><a href="#">Table list</a></li>
						<li class="tracy-tab-label"><a href="#">EntityManager</a></li>
					</ul>
					<div>
						<div class="tracy-tab-panel tracy-active">%s</div>
						<div class="tracy-tab-panel">%s</div>
						<div class="tracy-tab-panel">%s</div>
						<div class="tracy-tab-panel">%s</div>
					</div>
				</div>',
				$tab,
				$content,
				self::renderQueries(),
				self::renderTableList(),
				self::$entityManager !== null ? Dumper::toHtml(self::$entityManager) : '<i>Not set.</i>'
			),
		];
	}


	/**
	 * @return array{0: string, 1: string}
	 */
	private static function renderDriver(DriverException $e): array
	{
		$tab = null;
		$panel = null;
		if (str_contains($e->getMessage(), 'The server requested authentication method unknown to the client')) {
			$tab = 'Connection error';
			$panel = '<p>' . htmlspecialchars($e->getMessage()) . '</p>'
				. '<p>If the database is connected to the cloud (for example, DigitalOcean), an <b>enabled SSL mode</b> may be required for a functional connection.</p>'
				. '<p>To resolve this issue, you <b>must connect to your database manually</b> and run this SQL command:</p>'
				. '<pre class="code"><div>ALTER USER `myuser` IDENTIFIED WITH mysql_native_password BY \'mypassword\';</div></pre>'
				. '<p><b>Note about MySQL&nbsp;8</b></p>'
				. '<p>When running a PHP version before 7.1.16, or PHP 7.2 before 7.2.4, set MySQL 8 Server\'s default password plugin to mysql_native_password or else you will see errors similar to The server requested authentication method unknown to the client [caching_sha2_password] even when caching_sha2_password is not used.</p>'
				. '<p>This is because MySQL 8 defaults to caching_sha2_password, a plugin that is not recognized by the older PHP (mysqlnd) releases. Instead, change it by setting default_authentication_plugin=mysql_native_password in my.cnf. The caching_sha2_password plugin will be supported in a future PHP release. In the meantime, the mysql_xdevapi extension does support it.</p>'
				. '<p>Your PHP version is: <b>' . htmlspecialchars(PHP_VERSION) . '</b>.</p>'
				. '<a href="https://www.digitalocean.com/community/questions/how-to-change-caching_sha2_password-to-mysql_native_password-on-a-digitalocean-s-managed-mysql-database" target="_blank">More information</a>';
		}
		if (preg_match('/while executing \'(.+)\' with params (.+):(?:\n\s)+(.+)/', $e->getMessage(), $parser) === 1) {
			$tab = 'Driver error | ' . $parser[3];
			$panel = '<p>SQL:</p><pre class="code"><div>' . str_replace("\n", '', QueryUtils::highlight($parser[1])) . '</div></pre>'
				. '<p>With params:</p>' . Dumper::toHtml(json_decode($parser[2], true, 512, JSON_THROW_ON_ERROR));
		} elseif (preg_match('/while executing \'(.+)\'/', $e->getMessage(), $parser) === 1) {
			$tab = 'Driver error';
			$panel = '<p>SQL:</p><pre class="code"><div>' . str_replace("\n", '', QueryUtils::highlight($parser[1])) . '</div></pre>';
		} elseif (str_contains($e->getMessage(), 'Connection refused')) {
			$tab = 'Broken connection';
			$panel = '<p>The connection to the database was rejected by the database. '
				. 'Verify that your database is running and that you are using functional data for the connection '
				. '(there is often a problem of confusing host <b>localhost</b> vs. <b>127.0.0.1</b> '
				. 'or other host depending on your configuration).</p>'
				. (self::$entityManager !== null
					? '<p><b>Please check your local connection configuration</b> '
					. '(<i>You can change this configuration in the <b>local.neon</b> file</i>):</p>'
					. (static function (array $params): string {
						$return = '';
						foreach ($params as $key => $value) {
							$return .= '<tr><th>' . htmlspecialchars($key) . '</th>'
								. '<td>' . Dumper::toHtml($value) . '</td></tr>';
						}

						return '<table>' . $return . '</table>';
					})(self::$entityManager->getConnection()->getParams())
					. '<p>The usual settings are: Host: localhost, port: 3306 (for MySql).</p>'
					: '');
		}

		if (
			self::$entityManager !== null
			&& preg_match('/Table\s\\\'([^\\\']+)\\\'\sdoesn\\\'t\sexist/', $e->getMessage(), $parser) === 1
		) {
			$panel .= self::renderTableList();
		}

		return [
			$tab ?? 'Driver error',
			$panel ?? '<p>' . htmlspecialchars($e->getMessage()) . '</p>',
		];
	}


	private static function renderQuery(QueryException $e): string
	{
		if (
			self::$entityManager !== null
			&& preg_match('/Class\s(?<class>\S+)\shas no field or association named/', $e->getMessage(), $mapping) === 1
		) {
			$return = '';
			foreach (self::$entityManager->getClassMetadata($mapping['class'])->fieldMappings as $field) {
				$return .= '<tr>'
					. '<td>' . htmlspecialchars($field['fieldName']) . '</td>'
					. '<td>' . htmlspecialchars($field['columnName']) . '</td>'
					. '<td>' . htmlspecialchars($field['type']) . '</td>'
					. '</tr>';
			}

			return '<p>' . htmlspecialchars($e->getMessage()) . '</p>'
				. ($return !== ''
					? '<p><b>Available and valid fields:</b></p>'
					. '<table>'
					. '<tr><th>Field name</th><th>Column name</th><th>Type</th></tr>'
					. $return
					. '</table>'
					: '');
		}

		return '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
	}


	private static function renderMapping(MappingException $e): string
	{
		if (preg_match('/Class "([^"]+)"/', $e->getMessage(), $parser) === 1) {
			$className = $parser[1];
			if (class_exists($className) === true) {
				$fileName = null;
				$fileContent = null;
				$docComment = '';
				$startLine = 1;
				$entityAttributes = [];
				try {
					$ref = new \ReflectionClass($className);
					$fileName = (string) $ref->getFileName();
					$fileContent = \is_file($fileName)
						? (string) file_get_contents($fileName)
						: null;
					$startLine = (int) $ref->getStartLine();
					$docComment = trim((string) $ref->getDocComment());
					$entityAttributes = $ref->getAttributes(Entity::class);
				} catch (\ReflectionException) {
					// Silence is golden.
				}
				if ($fileName !== null && $fileContent !== null) {
					return '<p>File: <b>' . Helpers::editorLink($fileName, $startLine) . '</b> (class <b>' . htmlspecialchars($className) . '</b>)</p>'
						. '<p>A valid Doctrine entity must contain at least the #[Entity] attribute or "@Entity" annotation. See the <a href="https://www.doctrine-project.org/projects/doctrine-orm/en/2.7/reference/basic-mapping.html" target="_blank">documentation for more information</a>.</p>'
						. BlueScreen::highlightPhp(htmlspecialchars($fileContent, ENT_IGNORE), $startLine)
						. ($docComment !== '' ? '<p>Doc comment:</p><pre>' . htmlspecialchars($docComment) . '</pre>' : '')
						. ($entityAttributes !== [] ? '<p>Attributes:</p>' . Dumper::toHtml($entityAttributes) : '');
				}
			}

			return '<p>Class "' . htmlspecialchars($className) . '" does not exist!</p>'
				. '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
		}

		return '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
	}


	private static function renderTableList(): string
	{
		if (self::$entityManager === null) {
			return '<i>Not available.</i>';
		}
		try {
			$tableList = array_map(
				static fn(array $item): string => (string) (array_values($item)[0] ?? ''),
				self::$entityManager->getConnection()->executeQuery('show tables')->fetchAllAssociative(),
			);

			$panelMeta = [];
			foreach (self::$entityManager->getMetadataFactory()->getAllMetadata() as $metaData) {
				$metaTableName = $metaData->getTableName();
				$panelMeta[$metaTableName] = [
					'tableName' => $metaTableName,
					'className' => $metaData->getName(),
				];
			}

			$return = '';
			foreach ($tableList as $tableName) {
				$return .= '<tr>'
					. '<td>' . htmlspecialchars($tableName) . '</td>'
					. '<td>' . htmlspecialchars($panelMeta[$tableName]['className'] ?? '???') . '</td>'
					. '</tr>';
			}

			return '<table><tr><th>Table name</th><th>Entity class name</th></tr>' . $return . '</table>';
		} catch (\Throwable $e) {
			return '<i>' . htmlspecialchars($e->getMessage()) . '</i>';
		}
	}


	private static function renderQueries(): string
	{
		if (self::$panel === null) {
			return '<i>Panel has not been defined.</i>';
		}
		$events = self::$panel->getEvents();
		if ($events === []) {
			return '<h1>No queries</h1>';
		}

		$isTransaction = false;
		$select = 0;
		$insert = 0;
		$update = 0;
		$delete = 0;
		$other = 0;
		$timeBlocks = [];

		$tableContent = '';
		foreach ($events as $event) {
			$background = null;
			if ($event->getSql() === '"START TRANSACTION"') {
				$isTransaction = true;
				$background = 'rgb(204,255,204)';
			} elseif ($event->getSql() === '"COMMIT"' || $event->getSql() === '"ROLLBACK"') {
				$isTransaction = false;
				$background = 'rgb(253,169,157)';
			}
			if ($isTransaction === true && $background === null) {
				$background = 'rgb(255,244,204)';
			}

			$queryParser = explode(' ', strtoupper(trim($event->getSql())), 2);
			$durationMs = $event->getDuration() !== null ? $event->getDuration() * 1000 : null;

			if (isset($queryParser[1])) {
				switch ($queryParser[0]) {
					case 'SELECT':
						$select++;
						break;
					case 'INSERT':
						$insert++;
						break;
					case 'UPDATE':
						$update++;
						break;
					case 'DELETE':
						$delete++;
						break;
					default:
						$other++;
						break;
				}
			}

			$durationColor = QueryPanel::getPanelDurationColor($durationMs);
			$renderedQuery = '<tr>'
				. '<td' . ($durationColor !== null ? ' style="' . $durationColor . '"' : '') . '>'
				. ($durationMs !== null
					? '<span title="' . number_format($durationMs, 8, '.', ' ') . ' ms">'
					. number_format($durationMs, 2, '.', ' ')
					. '</span>'
					: '<span style="color:white;background:#bf0014;padding:2px 6px;border-radius:4px">Error</span>'
				)
				. '<br><i title="Request runtime delay time">' . number_format($event->getDelayTime(), 2, '.', '') . '</i>'
				. ($isTransaction ? '<br><span style="color:#bf0014">•</span>' : '')
				. '</td>'
				. '<td class="tracy-dbal-sql" ' . ($background ? ' style="background:' . $background . ' !important"' : '') . '>'
				. ($event->getDuration() === null
					? '<div style="color:white;background:#bf0014;text-align:center;padding:2px 6px;margin:8px 0;border-radius:4px">Error with processing this query!</div>'
					: '')
				. '<div style="background:white;padding:4px 8px">' . QueryUtils::highlight($event->getSql()) . '</div>'
				. ($event->getLocation() !== null
					? '<hr>' . Helpers::editorLink($event->getLocation()['file'], $event->getLocation()['line'])
					: ''
				) . '</td></tr>';
			$timeBlocks[] = '<td style="text-align:center;padding:0;' . ($durationColor !== null ? '' . $durationColor . '' : 'width:15px') . '">'
				. ((int) round($durationMs))
				. '</td>';

			if ($event->getDuration() !== null) {
				$tableContent .= $renderedQuery;
			}
		}

		$return = '<p>[';
		$return .= trim(
			($select > 0 ? $select . '&times;select ' : '')
			. ($update > 0 ? $update . '&times;update ' : '')
			. ($insert > 0 ? $insert . '&times;insert ' : '')
			. ($delete > 0 ? $delete . '&times;delete ' : '')
			. ($other > 0 ? $other . '&times;other</span>' : '')
		);
		$return .= ']</p>';
		if ($timeBlocks !== []) {
			$return .= '<table><tr>' . implode('', $timeBlocks) . '</tr></table>';
		}

		return sprintf('
		%s
		<table>
			<tr>
				<th style="max-width:48px !important">ms</th>
				<th>Query</th>
			</tr>
			%s
		</table>',
			$return,
			$tableContent,
		);
	}
}
