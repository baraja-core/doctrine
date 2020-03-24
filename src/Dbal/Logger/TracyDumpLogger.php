<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\Logger;


use stdClass;
use Tracy\Debugger;

/**
 * @deprecated since 2020-03-24
 */
final class TracyDumpLogger extends AbstractLogger
{
	public function stopQuery(): stdClass
	{
		$query = parent::stopQuery();

		Debugger::$maxLength = 100000;
		Debugger::barDump([
			'sql' => $query->sql,
			'args' => $query->params,
		], 'DBAL');
		Debugger::$maxLength = 150;

		return $query;
	}
}
