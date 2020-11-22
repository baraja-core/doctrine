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
use Tracy\Helpers;

final class TracyBlueScreenDebugger
{
	private static ?EntityManager $entityManager;


	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}


	public static function setEntityManager(EntityManager $entityManager): void
	{
		self::$entityManager = $entityManager;
	}


	/**
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
			return [
				'tab' => 'MappingException',
				'panel' => self::renderMapping($e),
			];
		}

		return self::renderCommon($e);
	}


	/**
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
	 * @return string[]
	 */
	private static function renderDriver(DriverException $e): array
	{
		$tab = null;
		$panel = null;
		if (strpos($e->getMessage(), 'The server requested authentication method unknown to the client') !== false) {
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
	 * @return string[]
	 */
	private static function renderQuery(QueryException $e): array
	{
		return [
			'tab' => 'Query error',
			'panel' => '<p>' . htmlspecialchars($e->getMessage()) . '</p>',
		];
	}


	private static function renderMapping(MappingException $e): string
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
					return '<p>File: <b>' . Helpers::editorLink($fileName, $startLine) . '</b> (class <b>' . htmlspecialchars($className) . '</b>)</p>'
						. '<p>A valid Doctrine entity must contain at least the "@Entity" annotation. See the <a href="https://www.doctrine-project.org/projects/doctrine-orm/en/2.7/reference/basic-mapping.html" target="_blank">documentation for more information</a>.</p>'
						. BlueScreen::highlightPhp(htmlspecialchars($fileContent, ENT_IGNORE, 'UTF-8'), $startLine)
						. '<p>Doc comment:</p>'
						. ($docComment === '' ? '<i>Doc comment is empty.</i>' : '<pre>' . htmlspecialchars($docComment) . '</pre>');
				}
			}

			return '<p>Class "' . htmlspecialchars($className) . '" does not exist!</p>'
				. '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
		}

		return '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
	}
}
