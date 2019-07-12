Baraja Doctrine database
========================

Simple easy to use, maximal performance database layer with connection to Doctrine and support for Nette 3.0.

This package install Doctrine automatically to your project with stable run.

How to install
--------------

Simple call Composer command:

```shell
composer require baraja/doctrine
```

In project `common.neon` you must define database credentials. Fully works example configuration is in `config.neon` file in this package.

This package support automatically install by PackageRegistrator. If you haven't, you should install manually by this manual.

All configuration you can define simply by parameters (stored in super-global array `parameters`).

For example:

```yaml
parameters:
	database:
		primary:
			host: 127.0.0.1
			dbname: sandbox
			user: root
			password: root
```

Package support to one database in specific moment now.

Best performance
----------------

When Doctrine is used poorly, it can be unnecessarily slow.

More details (Czech language): https://ondrej.mirtes.cz/doctrine-2-neni-pomala

This package use best-practices to performance increase. Set automatically `autoGenerateProxyClasses` to `false`, ProxyClasses will be generated when Doctrine needs.

For maximal performance is best save your generated meta data about entities to Redis: https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/caching.html

UUID
----

For unique record (entity) identification package defines trait `UuidIdentifier` with defined all basic best-practice configuration for your entity. ID will be generated automatically.

For better experience please insert two traits to all entities in project:

```php
<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Entity;


use Doctrine\ORM\Mapping as ORM;
use Nette\SmartObject;
use Baraja\Doctrine\UUID\UuidIdentifier;

/**
 * @ORM\Entity()
 */
class DatabaseEntity
{

	use UuidIdentifier; // <--- UUID trait for entity identifier.
	use SmartObject;    // <--- Strict class for better experience.
```

UUID will be generated automatically in PHP.

Manipulation with entities
--------------------------

Package defines DIC service `DoctrineHelper` with super useful methods.

- `getEntityVariants(string $entity, array $exclude = null): array`
- `getBestOfType(string $entity): string`
- `getTableNameByEntity(string $entity): string`
- `getRootEntityName(string $entity): string`
- `getDiscriminatorByEntity(string $entity): string`
- `remapEntityToBestType($from)`
- `remapEntity($from, $to)`



