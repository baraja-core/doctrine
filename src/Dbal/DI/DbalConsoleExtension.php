<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\DI;


use Doctrine\DBAL\Tools\Console\Command\ImportCommand;
use Doctrine\DBAL\Tools\Console\Command\ReservedWordsCommand;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\ServiceCreationException;
use Symfony\Component\Console\Application;

final class DbalConsoleExtension extends CompilerExtension
{
	public function loadConfiguration(): void
	{
		if (!class_exists('Symfony\Component\Console\Application')) {
			throw new ServiceCreationException('Missing Symfony\Component\Console\Application service');
		}
		if (PHP_SAPI !== 'cli') { // Skip if it's not CLI mode
			return;
		}

		$builder = $this->getContainerBuilder();

		// Helpers
		$builder->addDefinition($this->prefix('connectionHelper'))
			->setFactory(ConnectionHelper::class)
			->setAutowired(false);

		// Commands
		$builder->addDefinition($this->prefix('importCommand'))
			->setFactory(ImportCommand::class)
			->addTag('console.command', 'dbal:import')
			->setAutowired(false);

		$builder->addDefinition($this->prefix('reservedWordsCommand'))
			->setFactory(ReservedWordsCommand::class)
			->addTag('console.command', 'dbal:reserved-words')
			->setAutowired(false);

		$builder->addDefinition($this->prefix('runSqlCommand'))
			->setFactory(RunSqlCommand::class)
			->addTag('console.command', 'dbal:run-sql')
			->setAutowired(false);
	}


	public function beforeCompile(): void
	{
		if (PHP_SAPI !== 'cli') { // Skip if it's not CLI mode
			return;
		}

		$builder = $this->getContainerBuilder();

		/** @var ServiceDefinition $application */
		$application = $builder->getDefinitionByType(Application::class);

		// Register helpers
		$connectionHelper = $this->prefix('@connectionHelper');
		$application->addSetup(new Statement('$service->getHelperSet()->set(?, ?)', [$connectionHelper, 'db']));
	}
}
