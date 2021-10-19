<?php

declare(strict_types=1);

namespace Baraja\Doctrine\DBAL\DI;


use Doctrine\DBAL\Tools\Console\Command\ReservedWordsCommand;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Nette\DI\CompilerExtension;
use Nette\DI\ServiceCreationException;

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
		$builder->addDefinition($this->prefix('reservedWordsCommand'))
			->setFactory(ReservedWordsCommand::class)
			->addTag('console.command', 'dbal:reserved-words')
			->setAutowired(false);

		$builder->addDefinition($this->prefix('runSqlCommand'))
			->setFactory(RunSqlCommand::class)
			->addTag('console.command', 'dbal:run-sql')
			->setAutowired(false);
	}
}
