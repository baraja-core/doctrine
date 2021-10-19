<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\Tracy\BlueScreen;


use Baraja\Doctrine\DBAL\Utils\QueryUtils;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Query\QueryException;
use PDO;
use PDOException;
use Throwable;
use Tracy\Helpers;

/**
 * Inspired by https://github.com/Kdyby/Doctrine/blob/master/src/Kdyby/Doctrine/Diagnostics/Panel.php.
 */
final class DbalBlueScreen
{
	/**
	 * @return mixed[]|null
	 */
	public function __invoke(?Throwable $e): ?array
	{
		if ($e === null) {
			return null;
		}
		$prev = $e->getPrevious();
		if ($e instanceof DriverException) {
			$itemDriver = Helpers::findTrace($e->getTrace(), $e::class . '::driverExceptionDuringQuery');
			if ($prev && $itemDriver) {
				return [
					'tab' => 'SQL',
					'panel' => QueryUtils::highlight($itemDriver['args'][2]),
				];
			}
		} elseif ($e instanceof QueryException) {
			if ($prev && preg_match('~^(SELECT|INSERT|UPDATE|DELETE)\s+~i', $prev->getMessage())) {
				return [
					'tab' => 'DQL',
					'panel' => QueryUtils::highlight($prev->getMessage()),
				];
			}
		} elseif ($e instanceof PDOException) {
			$sql = (static function (\Throwable $e): ?string {
				if (isset($e->queryString)) {
					return $e->queryString;
				}
				$itemExecute = Helpers::findTrace($e->getTrace(), Connection::class . '::executeQuery');
				if ($itemExecute) {
					return $itemExecute['args'][0];
				}
				$itemQuery = Helpers::findTrace($e->getTrace(), PDO::class . '::query');
				if ($itemQuery) {
					return $itemQuery['args'][0];
				}
				$itemPrepare = Helpers::findTrace($e->getTrace(), PDO::class . '::prepare');
				if ($itemPrepare) {
					return $itemPrepare['args'][0];
				}

				return null;
			})($e);

			return $sql !== null ? [
				'tab' => 'SQL',
				'panel' => QueryUtils::highlight($sql),
			] : null;
		}

		return null;
	}
}
