<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


interface EntityManagerDependenciesAccessor
{
	/**
	 * @return EntityManagerDependencies
	 */
	public function get(): EntityManagerDependencies;
}