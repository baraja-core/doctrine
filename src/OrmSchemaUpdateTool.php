<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Contributte\Console\Application;
use Nette\DI\Container;
use Tracy\Debugger;

class OrmSchemaUpdateTool
{

	/**
	 * @var Container
	 */
	private static $container;

	public static function run(): void
	{
		if (self::$container === null) {
			return;
		}

		if (PHP_SAPI === 'cli' && preg_match('/^or?m?:sc?h?e?m?a?-?t?o?o?l?:up?d?a?t?e?$/', $_SERVER['argv'][1] ?? '')) {
			try {
				/** @var Application $application */
				$application = self::$container->getByType(Application::class);

				exit($application->run());
			} catch (\Throwable $e) {
				Debugger::log($e);

				echo $e->getMessage();

				exit($e->getCode() ? : 1);
			}
		}
	}

	/**
	 * @param Container $container
	 */
	public static function setContainer(Container $container): void
	{
		self::$container = $container;
	}

}