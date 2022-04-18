<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\Tracy\QueryPanel;


use Baraja\Doctrine\DBAL\Logger\AbstractLogger;
use Baraja\Doctrine\Logger\SqlParserUtils;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Tracy\Debugger;
use Tracy\IBarPanel;
use Tracy\ILogger;

final class QueryPanel extends AbstractLogger implements IBarPanel
{
	private const IconNoQuery = '<img alt="No queries" style="height: 16px; width: auto;" src="data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTkuMC4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iTGF5ZXJfMSIgeD0iMHB4IiB5PSIwcHgiIHZpZXdCb3g9IjAgMCAyODAuMDI3IDI4MC4wMjciIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDI4MC4wMjcgMjgwLjAyNzsiIHhtbDpzcGFjZT0icHJlc2VydmUiIHdpZHRoPSI1MTJweCIgaGVpZ2h0PSI1MTJweCI+CjxnPgoJPHBhdGggc3R5bGU9ImZpbGw6I0NDRDBEMjsiIGQ9Ik0xNy41MDIsNTIuNTA1djE3NS4wMTdjMCwyOC45ODMsNTQuODUsNTIuNTA1LDEyMi41MTIsNTIuNTA1czEyMi41MTItMjMuNTIyLDEyMi41MTItNTIuNTA1VjUyLjUwNSAgIEgxNy41MDJ6Ii8+Cgk8cGF0aCBzdHlsZT0iZmlsbDojQURCMEIyOyIgZD0iTTIyNy41MjIsMTIyLjUxMmM0LjgzOSwwLDguNzUxLTMuOTEyLDguNzUxLTguNzUxYzAtNC44My0zLjkxMi04Ljc1MS04Ljc1MS04Ljc1MSAgIGMtNC44MzksMC04Ljc1MSwzLjkyLTguNzUxLDguNzUxQzIxOC43NzEsMTE4LjYsMjIyLjY4MiwxMjIuNTEyLDIyNy41MjIsMTIyLjUxMnogTTIyNy41MjIsMTY2LjI2NiAgIGMtNC44MzksMC04Ljc1MSwzLjkyLTguNzUxLDguNzUxYzAsNC44MzksMy45MTIsOC43NTEsOC43NTEsOC43NTFjNC44MzksMCw4Ljc1MS0zLjkxMiw4Ljc1MS04Ljc1MSAgIEMyMzYuMjcyLDE3MC4xODcsMjMyLjM1MywxNjYuMjY2LDIyNy41MjIsMTY2LjI2NnogTTIyNy41MjIsMjI3LjUyMmMtNC44MzksMC04Ljc1MSwzLjkxMi04Ljc1MSw4Ljc1MXMzLjkxMiw4Ljc1MSw4Ljc1MSw4Ljc1MSAgIGM0LjgzOSwwLDguNzUxLTMuOTEyLDguNzUxLTguNzUxUzIzMi4zNTMsMjI3LjUyMiwyMjcuNTIyLDIyNy41MjJ6Ii8+Cgk8cGF0aCBzdHlsZT0iZmlsbDojQjdCQkJEOyIgZD0iTTE0MC4wMTQsMTY2LjI3NWM2Ny42NjIsMCwxMjIuNTEyLTI1LjM0MiwxMjIuNTEyLTU2LjYwOWMwLTEuNTkzLTAuMjM2LTMuMTUtMC41MTYtNC43MTcgICBjLTUuMTk4LDI5LjA1My01Ny43ODIsNTEuODkzLTEyMS45OTYsNTEuODkzUzIzLjIyNSwxMzQuMDExLDE4LjAxOCwxMDQuOTQ5Yy0wLjI4LDEuNTY2LTAuNTE2LDMuMTI0LTAuNTE2LDQuNzE3ICAgQzE3LjUwMiwxNDAuOTI0LDcyLjM0MywxNjYuMjc1LDE0MC4wMTQsMTY2LjI3NXogTTE0MC4wMTQsMjE4LjA5OGMtNjQuMjE0LDAtMTE2Ljc4OS0yMi44MjItMTIxLjk5Ni01MS44OTMgICBjLTAuMjgsMS41NjYtMC41MTYsMy4xMjQtMC41MTYsNC43MTdjMCwzMS4yNDksNTQuODUsNTYuNjA5LDEyMi41MTIsNTYuNjA5czEyMi41MTItMjUuMzQyLDEyMi41MTItNTYuNjA5ICAgYzAtMS42MDEtMC4yMzYtMy4xNS0wLjUxNi00LjcxN0MyNTYuODIsMTk1LjI0OSwyMDQuMjM2LDIxOC4wOTgsMTQwLjAxNCwyMTguMDk4eiIvPgoJPHBhdGggc3R5bGU9ImZpbGw6I0MyQzVDNzsiIGQ9Ik00My43NTQsMjU5LjkzNVY1Mi41MDVIMTcuNTAydjE3NS4wMTdDMTcuNTAyLDIzOS43NTYsMjcuMzU1LDI1MS4wMSw0My43NTQsMjU5LjkzNXoiLz4KCTxnPgoJCTxwYXRoIHN0eWxlPSJmaWxsOiNCMkI1Qjc7IiBkPSJNNDMuNzU0LDIwNS44NjR2LTkuNDg2Yy0xNC4zNjktOC40NjItMjMuNjk3LTE4LjgyMy0yNS43MzYtMzAuMTczICAgIGMtMC4yOCwxLjU2Ni0wLjUxNiwzLjEyNC0wLjUxNiw0LjcxN0MxNy41MDIsMTg0LjEyNywyNy4zNTUsMTk2LjIzOCw0My43NTQsMjA1Ljg2NHogTTQzLjc1NCwxNDQuNjA4di05LjQ3NyAgICBjLTE0LjM2OS04LjQ2Mi0yMy42OTctMTguODMyLTI1LjczNi0zMC4xOWMtMC4yOCwxLjU3NS0wLjUxNiwzLjEzMy0wLjUxNiw0LjcyNUMxNy41MDIsMTIyLjg3MSwyNy4zNTUsMTM0Ljk4Miw0My43NTQsMTQ0LjYwOHoiLz4KCTwvZz4KCTxwYXRoIHN0eWxlPSJmaWxsOiNFNEU3RTc7IiBkPSJNMTQwLjAxNCwwYzY3LjY2MiwwLDEyMi41MTIsMjMuNTE0LDEyMi41MTIsNTIuNTA1cy01NC44NSw1Mi41MDUtMTIyLjUxMiw1Mi41MDUgICBTMTcuNTAyLDgxLjQ5NywxNy41MDIsNTIuNTA1UzcyLjM0MywwLDE0MC4wMTQsMHoiLz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8L3N2Zz4K" />';

	private const IconQuery = '<img alt="Queries" style="height: 16px; width: auto;" src="data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTkuMC4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDUzIDUzIiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3IDAgMCA1MyA1MzsiIHhtbDpzcGFjZT0icHJlc2VydmUiIHdpZHRoPSI1MTJweCIgaGVpZ2h0PSI1MTJweCI+CjxwYXRoIHN0eWxlPSJmaWxsOiM0MjRBNjA7IiBkPSJNNTAuNDU1LDhMNTAuNDU1LDhDNDkuNzI0LDMuNTM4LDM5LjI4MSwwLDI2LjUsMFMzLjI3NiwzLjUzOCwyLjU0NSw4bDAsMEgyLjV2MC41VjIwdjAuNVYyMXYxMXYwLjUgIFYzM3YxMmgwLjA0NWMwLjczMSw0LjQ2MSwxMS4xNzUsOCwyMy45NTUsOHMyMy4yMjQtMy41MzksMjMuOTU1LThINTAuNVYzM3YtMC41VjMyVjIxdi0wLjVWMjBWOC41VjhINTAuNDU1eiIvPgo8Zz4KCTxwYXRoIHN0eWxlPSJmaWxsOiM0MjRBNjA7IiBkPSJNMjYuNSw0MWMtMTMuMjU1LDAtMjQtMy44MDYtMjQtOC41VjQ1aDAuMDQ1YzAuNzMxLDQuNDYxLDExLjE3NSw4LDIzLjk1NSw4czIzLjIyNC0zLjUzOSwyMy45NTUtOCAgIEg1MC41VjMyLjVDNTAuNSwzNy4xOTQsMzkuNzU1LDQxLDI2LjUsNDF6Ii8+Cgk8cGF0aCBzdHlsZT0iZmlsbDojNDI0QTYwOyIgZD0iTTIuNSwzMnYwLjVjMC0wLjE2OCwwLjAxOC0wLjMzNCwwLjA0NS0wLjVIMi41eiIvPgoJPHBhdGggc3R5bGU9ImZpbGw6IzQyNEE2MDsiIGQ9Ik01MC40NTUsMzJjMC4wMjcsMC4xNjYsMC4wNDUsMC4zMzIsMC4wNDUsMC41VjMySDUwLjQ1NXoiLz4KPC9nPgo8Zz4KCTxwYXRoIHN0eWxlPSJmaWxsOiNFRkNFNEE7IiBkPSJNMjYuNSwyOWMtMTMuMjU1LDAtMjQtMy44MDYtMjQtOC41VjMzaDAuMDQ1YzAuNzMxLDQuNDYxLDExLjE3NSw4LDIzLjk1NSw4czIzLjIyNC0zLjUzOSwyMy45NTUtOCAgIEg1MC41VjIwLjVDNTAuNSwyNS4xOTQsMzkuNzU1LDI5LDI2LjUsMjl6Ii8+Cgk8cGF0aCBzdHlsZT0iZmlsbDojRUZDRTRBOyIgZD0iTTIuNSwyMHYwLjVjMC0wLjE2OCwwLjAxOC0wLjMzNCwwLjA0NS0wLjVIMi41eiIvPgoJPHBhdGggc3R5bGU9ImZpbGw6I0VGQ0U0QTsiIGQ9Ik01MC40NTUsMjBjMC4wMjcsMC4xNjYsMC4wNDUsMC4zMzIsMC4wNDUsMC41VjIwSDUwLjQ1NXoiLz4KPC9nPgo8ZWxsaXBzZSBzdHlsZT0iZmlsbDojN0ZBQkRBOyIgY3g9IjI2LjUiIGN5PSI4LjUiIHJ4PSIyNCIgcnk9IjguNSIvPgo8Zz4KCTxwYXRoIHN0eWxlPSJmaWxsOiM3MzgzQkY7IiBkPSJNMjYuNSwxN2MtMTMuMjU1LDAtMjQtMy44MDYtMjQtOC41VjIxaDAuMDQ1YzAuNzMxLDQuNDYxLDExLjE3NSw4LDIzLjk1NSw4czIzLjIyNC0zLjUzOSwyMy45NTUtOCAgIEg1MC41VjguNUM1MC41LDEzLjE5NCwzOS43NTUsMTcsMjYuNSwxN3oiLz4KCTxwYXRoIHN0eWxlPSJmaWxsOiM3MzgzQkY7IiBkPSJNMi41LDh2MC41YzAtMC4xNjgsMC4wMTgtMC4zMzQsMC4wNDUtMC41SDIuNXoiLz4KCTxwYXRoIHN0eWxlPSJmaWxsOiM3MzgzQkY7IiBkPSJNNTAuNDU1LDhDNTAuNDgyLDguMTY2LDUwLjUsOC4zMzIsNTAuNSw4LjVWOEg1MC40NTV6Ii8+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPGc+CjwvZz4KPC9zdmc+Cg==" />';

	public static int $maxLength = 1_000;

	private static ?CacheProvider $cache = null;

	private static bool $invalidCache = false;

	/** @var string[] */
	private static array $durationColors = [
		500 => 'background:#f30808 !important;color:white',
		300 => 'background:#ff5f17 !important;color:white',
		150 => 'background:#fb834d !important;color:white',
		75 => 'background:#fb9567 !important;color:white',
		15 => 'background:#ffae89 !important;color:black',
		5 => 'background:#fbccb7 !important;color:black',
	];

	private Connection $connection;


	public function setConnection(Connection $connection): void
	{
		$this->connection = $connection;
	}


	public static function setCache(?CacheProvider $cache): void
	{
		self::$cache = $cache;
	}


	public static function setInvalidCache(bool $invalidCache): void
	{
		self::$invalidCache = $invalidCache;
	}


	public static function getPanelDurationColor(?float $durationMs): ?string
	{
		if ($durationMs === null || $durationMs < 5) {
			return null;
		}
		foreach (self::$durationColors as $time => $color) {
			if ($time <= $durationMs) {
				return $color;
			}
		}

		return null;
	}


	/**
	 * @internal reserved for configuration
	 */
	public static function addDurationColor(int $time, string $backgroundColor, string $textColor): void
	{
		self::$durationColors[$time] = 'background:' . $backgroundColor . ' !important;color:' . $textColor;
	}


	/**
	 * @param array<int|string, mixed>|null $params
	 * @param array<int, Type|int|string|null>|array<string, Type|int|string|null>|null $types
	 */
	public function startQuery($sql, ?array $params = null, ?array $types = null): void
	{
		if ($params !== null && $params !== []) {
			try {
				[$sql, $params, $types] = SqlParserUtils::expandListParameters($sql, $params, $types ?? []);
			} catch (\Throwable) {
				// Silence is golden.
			}

			$sql = str_replace(['%', '?'], ['%%', '%s'], $sql);
			try {
				$query = vsprintf(
					$sql,
					(function () use ($params, $types): array {
						$quotedParams = [];
						foreach ($params as $typeIndex => $value) {
							$type = $types[$typeIndex] ?? null;
							/** @phpstan-ignore-next-line */
							$quotedParams[] = $this->connection->quote($value ?? '', $type);
						}

						return $quotedParams;
					})(),
				);
			} catch (\Throwable $e) {
				$query = $sql;
				Debugger::log($e, ILogger::WARNING);
			}
		} else {
			$query = $sql;
		}

		/** @phpstan-ignore-next-line */
		parent::startQuery($query, $params, $types);
	}


	public function getTab(): string
	{
		$totalTime = $this->getTimer();
		$totalTimeColor = self::getPanelDurationColor($totalTime);
		$count = $this->getCounter();

		return '<span title="Doctrine: ' . $count . ' queries">'
			. '<span class="tracy-label">'
			. ($count > 0 ? self::IconQuery : self::IconNoQuery)
			. '&nbsp;' . $count
			. ($totalTime > 0 ? ' / <span' . ($totalTimeColor !== null ? ' style="' . $totalTimeColor . ';padding:0 3px"' : '') . '>'
				. number_format($totalTime, 1, '.', ' ') . ' ms</span>'
				: '')
			. '</span>'
			. '</span>';
	}


	public function getPanel(): string
	{
		ob_start();

		$args = [
			'parameters' => array_merge($this->connection->getParams(), ['password' => '****']),
			'cache' => self::$cache,
			'invalidCache' => self::$invalidCache,
			'connected' => $this->connection->isConnected(),
			'queriesCount' => $this->getCounter(),
			'totalTime' => $this->getTimer(),
			'events' => $this->getEvents(),
		];

		/** @phpstan-ignore-next-line */
		extract($args, EXTR_OVERWRITE);

		require __DIR__ . '/templates/panel.phtml';

		return (string) ob_get_clean();
	}
}
