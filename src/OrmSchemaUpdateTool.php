<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Contributte\Console\Application;
use Nette\Application\IPresenter;
use Nette\Application\Responses\VoidResponse;
use Nette\Application\UI\Presenter;
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
		/** @var \Nette\Application\Application $application */
		$application = $container->getByType(\Nette\Application\Application::class);

		$application->onPresenter[] = function (\Nette\Application\Application $application, IPresenter $presenter): void {
			if ($presenter instanceof Presenter) {
				$presenter->onStartup[] = function (Presenter $presenter): void {
					$presenter->sendResponse(new VoidResponse);
				};
			}
		};

		self::$container = $container;
	}

}
