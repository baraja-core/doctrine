<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\PackageManager\Composer\BaseTask;
use Baraja\PackageManager\Helpers;

/**
 * Priority: 5
 */
final class OrmSchemaUpdateTask extends BaseTask
{

	/**
	 * @return bool
	 */
	public function run(): bool
	{
		echo 'BasePath: ' . ($basePath = \dirname(__DIR__, 4) . '/') . "\n\n";

		if (Helpers::functionIsAvailable('shell_exec')) {
			if (preg_match('/^PHP\s*((\d+)\.\d+)/', str_replace("\n", ' ', shell_exec('php -v')), $phpVersionParser)) {
				$command = ($phpVersionParser[2] === '5' ? 'php7.1' : 'php')
					. ' ' . rtrim(str_replace('\\', '/', $basePath), '/') . '/www/index.php o:s:u -f --dump-sql';

				echo 'Using PHP ' . $phpVersionParser[1] . "\n";
				echo 'Command: ' . $command;
				echo "\n\n";

				if (preg_match('/^ERROR:/', $output = trim($this->liveExecuteCommand($command)))) {
					Helpers::terminalRenderError($output);
					if (\is_file($logPath = $basePath . '/log/exception.log') === true) {
						$logLine = trim((string) ($data[\count($data = file($logPath)) - 1] ?? '???'));

						if (preg_match('/(exception--[\d-]+--[a-f\d]+\.html)/', $logLine, $logLineParser)) {
							Helpers::terminalRenderError('Logged to file: ' . $logLineParser[1]);
						}

						Helpers::terminalRenderError($logLine);
					}
				}
			}
		} else {
			Helpers::terminalRenderError(
				'Function shell_exec() is not available here.' . "\n\n"
				. 'Please run command "php www/index.php o:s:u -f --dump-sql" manually.'
			);
		}

		return true;
	}


	/**
	 * @return string
	 */
	public function getName(): string
	{
		return 'o:s:u -f --dump-sql';
	}


	/**
	 * Execute the given command by displaying console output live to the user.
	 *
	 * @param string
	 * @return string
	 */
	private function liveExecuteCommand(string $command): string
	{
		while (@ob_end_flush()) {
			continue;
		}

		$proc = popen($command . ' 2>&1 ; echo Exit status : $?', 'r');
		$output = '';

		while (feof($proc) === false) {
			$liveOutput = fread($proc, 4096);
			$output .= $liveOutput;
			echo (string) $liveOutput;
			@flush();
		}

		pclose($proc);

		return preg_match('/\d+$/', $output, $matches)
			? str_replace('Exit status: ' . ($matches[0] ?? ''), '', $output)
			: '';
	}
}
