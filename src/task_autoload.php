<?php

declare(strict_types=1);

// --package-registrator-task--

if (PHP_SAPI !== 'cli' || class_exists(\Baraja\PackageManager\Composer\TaskManager::class) === false) {
	return;
}

\Baraja\PackageManager\Composer\TaskManager::get()->addTask(
	new \Baraja\Doctrine\OrmSchemaUpdateTask(\Baraja\PackageManager\PackageRegistrator::get()),
);
