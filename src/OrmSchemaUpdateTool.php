<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Contributte\Console\Application;
use Nette\Application\IPresenter;
use Nette\Application\Responses\VoidResponse;
use Nette\Application\UI\Presenter;
use Nette\DI\Container;
use Nette\Utils\Finder;
use Tracy\Debugger;

final class OrmSchemaUpdateTool
{

	/** @var Container */
	private static $container;


	/**
	 * @internal
	 */
	public static function run(): void
	{
		if (self::$container === null) {
			echo 'Error: Container was not set.';

			return;
		}

		if (PHP_SAPI === 'cli' && preg_match('/^or?m?:sc?h?e?m?a?-?t?o?o?l?:up?d?a?t?e?$/', $_SERVER['argv'][1] ?? '')) {
			self::classChecker(self::$container);

			try {
				/** @var Application $application */
				$application = self::$container->getByType(Application::class);

				$runCode = $application->run();
				echo "\n" . 'Exit with code #' . $runCode;
				exit($runCode);
			} catch (\Throwable $e) {
				Debugger::log($e);
				echo $e->getMessage();

				$exitCode = $e->getCode() ?: 1;
				echo "\n" . 'Exit with code #' . $exitCode;
				exit($exitCode);
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


	/**
	 * @param Container $container
	 */
	private static function classChecker(Container $container): void
	{
		echo 'Checking...' . "\n";
		$loadedFiles = get_included_files();

		foreach (Finder::find('*.php')->from(\dirname($container->getParameters()['wwwDir'])) as $path => $file) {
			if (\in_array($path, $loadedFiles, true)) {
				continue;
			}

			if (strpos((string) file_get_contents($path), '@ORM\Entity') !== false) {
				echo preg_replace('/^.*\/([^\/]+)$/', '$1', str_replace('\\', '/', $path)) . "\n";
				require $path;
			}
		}

		echo "\n\n" . 'All files are OK.' . "\n\n";
	}
}
