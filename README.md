Baraja Doctrine database
========================

![Integrity check](https://github.com/baraja-core/doctrine/workflows/Integrity%20check/badge.svg)

A simple and easy to use, maximum performance database layer with connection to Doctrine, which allows you to use all the advantages of OOP and also has **support for Nette 3**.

This package automatically installs Doctrine to your project (also sets everything up in the configuration) and runs stably.

📦 Installation & Basic Usage
-----------------------------

This package can be installed using [Package Manager](https://github.com/baraja-core/package-manager) which is also part of the Baraja [Sandbox](https://github.com/baraja-core/sandbox). If you are not using it, you will have to install the package manually using this guide.

A model configuration can be found in the `common.neon` file inside the root of the package.

To manually install the package call Composer and execute the following command:

```shell
$ composer require baraja-core/doctrine
```

In the project's `common.neon` you have to define the database credentials. A fully working example of configuration can be found in the `common.neon` file inside this package. You can define the configuration simply using `baraja.database` extension.

For example:

```yaml
baraja.database:
   connection:
      host: 127.0.0.1
      dbname: sandbox
      user: root
      password: root
```

For now the package supports only the connection to one database.

Possible connection options: `url`, `pdo`, `memory`, `driver`, `driverClass`, `driverOptions`, `unix_socket`, `host`, `port`, `dbname`, `servicename`, `user`, `password`, `charset`, `portability`, `fetchCase`, `persistent`, `types`, `typesMapping`, `wrapperClass`.

⚙️ Drivers
----------

In default settings Doctrine use `MySql` driver.

You can rewrite it for example for Postgres:

In your `common.neon` simple type:

```yaml
dbal:
   connection:
      driverClass: Baraja\Doctrine\Driver\Postgres\PDOPgSqlDriver
```

🗺️ Entity mapping
------------------

In order for Doctrine to know which classes are **entities** and which **application logic**, it is necessary to set up a mapping.

For mapping, it is necessary to set the introductory part of the namespace entities and the directory where they occur in the project common.neon. A relative path can also be used.

For example:

```yaml
orm.annotations:
   paths:
      App\Baraja\Entity: %rootDir%/app/model/Entity
```

You can also specify the `ignore` key, which disables browsing a specific directory.

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

> **TIP:** If you are using [Package Manager](https://github.com/baraja-core/package-manager), you can simply call the `composer dump` command.

🚀 Performance Benchmarks
-------------------------

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

📄 License
-----------

`baraja-core/doctrine` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/doctrine/blob/master/LICENSE) file for more details.
