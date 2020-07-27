<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


use Baraja\PackageManager\Composer\BaseTask;
use Baraja\PackageManager\Helpers;
use Baraja\PackageManager\PackageRegistrator;
use Contributte\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Tracy\Debugger;

/**
 * Priority: 99
 */
final class OrmSchemaUpdateTask extends BaseTask
{
	public function run(): bool
	{
		try {
			if (PackageRegistrator::getCiDetect() !== null) {
				echo 'CI environment detected: Schema generating skipped.';

				return true;
			}
		} catch (\Exception $e) {
		}

		echo 'Using PHP version: ' . PHP_VERSION . "\n\n";

		try {
			/** @var Application $app */
			$app = $this->getContainer()->getByType(Application::class);
			$app->setAutoExit(false);
			$app->setCatchExceptions(false);
			$app->run(new ArgvInput(['index.php', 'orm:schema-tool:update', '-f', '--dump-sql']));
		} catch (\Throwable $e) {
			if (\class_exists(Debugger::class) === true) {
				Debugger::log($e, 'critical');
			}
			if (preg_match('/The annotation "([^"]+)" in class (\S+) was never imported/', $e->getMessage(), $annotation)) {
				$this->unknownAnnotationInfo($annotation[1], $annotation[2]);
			} else {
				Helpers::terminalRenderError($e->getMessage());
				Helpers::terminalRenderCode($e->getFile(), $e->getLine());
			}

			return false;
		}

		return true;
	}


	public function getName(): string
	{
		return 'Update Doctrine database schema';
	}


	private function unknownAnnotationInfo(string $annotation, string $class): void
	{
		Helpers::terminalRenderError('The annotation "' . $annotation . '" in class "' . $class . '" was never imported.');
		Helpers::terminalRenderError('Did you maybe forget to add a "use" statement for this annotation?');
		echo "\n\n";
		echo 'To solve this issue:' . "\n";
		echo 'This error is typical when you map entities to places where they do not belong.' . "\n";
		echo 'You should now modify your NEON configuration file to include only the mapping to the directory where your entities are located.' . "\n\n\n";
		echo 'Configuration example (paste into your project `common.neon`):' . "\n\n";
		echo '| orm.annotations:' . "\n";
		echo '|    paths:' . "\n";
		echo '|       App\Baraja\Entity: %rootDir%/app/model/Entity' . "\n\n\n";
		echo 'Complete configuration information is available in the official Baraja Doctrine documentation: https://github.com/baraja-core/doctrine';
	}
}
