<?php

declare(strict_types=1);

namespace Baraja\Doctrine\ORM\Mapping;


use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver as DoctrineAnnotationDriver;

final class AnnotationDriver extends DoctrineAnnotationDriver
{
	public function __construct(Reader $reader, EntityAnnotationManager $annotationManager)
	{
		parent::__construct($reader, $paths = $annotationManager->getPaths());
		$this->reader = $reader;
		$this->paths = $paths;
	}
}
