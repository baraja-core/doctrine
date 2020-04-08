<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\PackageManager\Composer\BaseTask;
use Baraja\PackageManager\Helpers;
use Contributte\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Priority: 99
 */
final class OrmSchemaUpdateTask extends BaseTask
{

	/**
	 * @return bool
	 */
	public function run(): bool
	{
		echo 'Using PHP version: ' . PHP_VERSION . "\n\n";

		try {
			/** @var Application $app */
			$app = $this->getContainer()->getByType(Application::class);
			$app->setAutoExit(false);
			$app->run(new ArgvInput(['index.php', 'orm:schema-tool:update', '-f', '--dump-sql']));
		} catch (\Throwable $e) {
			Helpers::terminalRenderError($e->getMessage());
			Helpers::terminalRenderCode($e->getFile(), $e->getLine());

			return false;
		}

		return true;
	}


	/**
	 * @return string
	 */
	public function getName(): string
	{
		return 'Update Doctrine database schema';
	}
}
