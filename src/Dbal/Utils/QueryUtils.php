<?php

declare(strict_types=1);


namespace Baraja\Doctrine\DBAL\Utils;

final class QueryUtils
{

	/**
	 * @param string $sql
	 * @return string
	 */
	public static function highlight(string $sql): string
	{
		static $keywords1 = 'SELECT|(?:ON\s+DUPLICATE\s+KEY)?UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?|DELETE|CALL|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE';
		static $keywords2 = 'ALL|DISTINCT|DISTINCTROW|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|[RI]?LIKE|REGEXP|TRUE|FALSE|WITH|INSTANCE\s+OF';

		// insert new lines
		$sql = ' ' . $sql . ' ';
		$sql = preg_replace("#(?<=[\\s,(])($keywords1)(?=[\\s,)])#i", "\n\$1", $sql);

		// reduce spaces
		$sql = (string) preg_replace('#[ \t]{2,}#', ' ', $sql);

		$sql = wordwrap($sql, 100);
		$sql = (string) preg_replace('#([ \t]*\r?\n){2,}#', "\n", $sql);

		// syntax highlight
		self::getCounter(true);
		$sql = htmlspecialchars($sql, ENT_IGNORE, 'UTF-8');
		$sql = (string) preg_replace_callback(
			"#(/\\*.+?\\*/)|(\\*\\*.+?\\*\\*)|(?<=[\\s,(])($keywords1)(?=[\\s,)])|(?<=[\\s,(=])($keywords2)(?=[\\s,)=])#is",
			function ($matches) {
				if (!empty($matches[1])) { // comment
					return '<em style="color:gray">' . $matches[1] . '</em>';
				}

				if (!empty($matches[2])) { // error
					return '<strong style="color:red">' . $matches[2] . '</strong>';
				}

				if (!empty($matches[3])) { // most important keywords
					return (self::getCounter() > 1 ? '<br>' : '') . '<strong style="color:blue">' . $matches[3] . '</strong>';
				}

				if (!empty($matches[4])) { // other keywords
					return '<strong style="color:green">' . $matches[4] . '</strong>';
				}

				return '';
			},
			$sql
		);

		return '<span class="dump" style="font-family: monospace">'
			. trim((string) preg_replace('/<\/strong>\s+/', '</strong>&nbsp;', $sql))
			. '</span>' . "\n";
	}

	/**
	 * @param bool $reset
	 * @return int
	 */
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
