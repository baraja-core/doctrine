Baraja Doctrine database
========================

A simple and easy to use, maximum performance database layer with connection to Doctrine, which allows you to use all the advantages of OOP and also has support for Nette 3.0.

This package automatically installs Doctrine to your project (also setting everything up in the configuration) and runs stably.

**READY FOR NETTE 3.0!**

How to install
--------------
This package can be installed using [PackageRegistrator](https://github.com/baraja-core/package-manager) which is also part of the Baraja [Sandbox](https://github.com/baraja-core/sandbox). If you are not using it, you have to install the package manually following this guide.

A model configuration can be found in the config.neon file inside the root of the package.

To manually install the package call Composer and execute the following command:

```shell
composer require baraja-core/doctrine
```

In the projects `common.neon` you have to define the database credentials. A fully working example of configuration can be found in the `config.neon` file inside this package.

You can define the configuration simply using parameters (stored in the super-global array `parameters`).

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

For now the package supports only the connection to one database.

Drivers
-------

In default settings Doctrine use `MySql` driver.

You can rewrite it for example for Postgres:

In your `common.neon` simple type:

```yaml
dbal:
	connection:
		driverClass: Baraja\Doctrine\Driver\Postgres\PDOPgSqlDriver
```

Generate database structure from entities
-----------------------------------------

This package implements a bridge to automatically execute Doctrine commands.

For example you can simply call:

```shell
php www/index.php o:s:u -f --dump-sql
```

The command `o:s:u` means `orm:schema-tool:update`.

- `-f` is `flush` to execute changes in SQL,
- `--dump-sql` renders the list of SQL commands that will be executed.

If everything will work fine, the command will create the table `core__database_slow_query` which is defined in this package and is ready for logging slow queries.

Best performance
----------------

When Doctrine is used poorly, it can be unnecessarily slow.

For more details (in Czech language): https://ondrej.mirtes.cz/doctrine-2-neni-pomala

This package uses best-practices to increase the performance. It sets automatically `autoGenerateProxyClasses` to `false`, ProxyClasses will be generated when needed by Doctrine.

For maximum performance is best to save the generated meta data about your entities using Redis: https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/caching.html

UUID
----

**TIP:** Read more about [UUID binary performance](https://php.baraja.cz/uuid-performance) (czech language)

For unique record (entity) identification the package defines the trait `UuidIdentifier` or `UuidBinaryIdentifier` with already defined all basic best-practice configurations for your entity. The ID will be generated automatically.

For a better experience please insert two traits to all the entities in your project:

```php
<?php

declare(strict_types=1);

namespace Baraja\Doctrine\Entity;


use Doctrine\ORM\Mapping as ORM;
use Nette\SmartObject;
use Baraja\Doctrine\UUID\UuidBinaryIdentifier;

/**
 * @ORM\Entity()
 */
class DatabaseEntity
{

	use UuidBinaryIdentifier; // <--- UUID trait for entity identifier.
	use SmartObject;          // <--- Strict class for better experience.
```

UUID will be generated automatically in PHP.

Entities manipulation
--------------------------

The package defines a DIC service `DoctrineHelper` with super useful methods.

- `getEntityVariants(string $entity, array $exclude = null): array`
- `getBestOfType(string $entity): string`
- `getTableNameByEntity(string $entity): string`
- `getRootEntityName(string $entity): string`
- `getDiscriminatorByEntity(string $entity): string`
- `remapEntityToBestType($from)`
- `remapEntity($from, $to)`

More information is in the method doc. comment.

