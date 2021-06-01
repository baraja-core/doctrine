<?php

declare(strict_types=1);

// --package-registrator-task--

use Baraja\Doctrine\OrmSchemaUpdateTask;
use Baraja\PackageManager\Composer\TaskManager;
use Baraja\PackageManager\PackageRegistrator;

if (PHP_SAPI !== 'cli' || class_exists(TaskManager::class) === false) {
	return;
}

TaskManager::get()->addTask(
	new OrmSchemaUpdateTask(PackageRegistrator::get()),
);
