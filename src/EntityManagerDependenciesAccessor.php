<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


interface EntityManagerDependenciesAccessor
{
	public function get(): EntityManagerDependencies;
}
