<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\Utils;


final class QueryUtils
{
	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . static::class . ' is static and cannot be instantiated.');
	}


	public static function highlight(string $sql): string
	{
		static $keywords1 = 'SELECT|(?:ON\s+DUPLICATE\s+KEY)?UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?'
			. '|DELETE|CALL|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET'
			. '|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE';
		static $keywords2 = 'ALL|DISTINCT|DISTINCTROW|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|[RI]?LIKE'
			. '|REGEXP|TRUE|FALSE|WITH|INSTANCE\s+OF';

		// insert new lines
		$sql = ' ' . $sql . ' ';
		$sql = (string) preg_replace("#(?<=[\\s,(])($keywords1)(?=[\\s,)])#i", "\n\$1", $sql);

		// reduce spaces
		$sql = (string) preg_replace('#[ \t]{2,}#', ' ', $sql);

		$sql = wordwrap($sql, 100);
		$sql = (string) preg_replace('#([ \t]*\r?\n){2,}#', "\n", $sql);

		// syntax highlight
		self::getCounter(true);
		$sql = (string) preg_replace_callback(
			"#(/\\*.+?\\*/)|(\\*\\*.+?\\*\\*)|(?<=[\\s,(])($keywords1)(?=[\\s,)])|(?<=[\\s,(=])($keywords2)(?=[\\s,)=])#is",
			static function (array $matches): string {
				if (($matches[1] ?? '') !== '') { // comment
					return '<em style="color:#424242" style="font-family:monospace">' . $matches[1] . '</em>';
				}
				if (($matches[2] ?? '') !== '') { // error
					return '<strong style="color:#c62828" style="font-family:monospace">' . $matches[2] . '</strong>';
				}
				if (($matches[3] ?? '') !== '') { // most important keywords
					return (self::getCounter() > 1 ? '<br>' : '')
						. '<strong style="color:#283593" style="font-family:monospace">' . $matches[3] . '</strong>';
				}
				if (($matches[4] ?? '') !== '') { // other keywords
					return '<strong style="color:#2e7d32" style="font-family:monospace">' . $matches[4] . '</strong>';
				}

				return '';
			},
			htmlspecialchars($sql, ENT_IGNORE),
		);

		return '<span class="dump">'
			. trim((string) preg_replace('/<\/strong>\s+/', '</strong>&nbsp;', $sql))
			. '</span>' . "\n";
	}


	private static function getCounter(bool $reset = false): int
	{
		static $counter = 0;

		if ($reset === true) {
			$counter = 0;
		} else {
			$counter++;
		}

		return $counter;
	}
}
