<?php

declare(strict_types=1);

namespace Baraja\Doctrine;


interface DoctrineHelperAccessor
{

	/**
	 * @return DoctrineHelper
	 */
	public function get(): DoctrineHelper;

}