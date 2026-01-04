<div align='center'>
  <picture>
    <source media='(prefers-color-scheme: dark)' srcset='https://cdn.brj.app/images/brj-logo/logo-regular.png'>
    <img src='https://cdn.brj.app/images/brj-logo/logo-dark.png' alt='BRJ logo'>
  </picture>
  <br>
  <a href="https://brj.app">BRJ organisation</a>
</div>
<hr>

# Baraja Doctrine Database

![Integrity check](https://github.com/baraja-core/doctrine/workflows/Integrity%20check/badge.svg)

A simple and easy to use, maximum performance database layer with connection to Doctrine ORM, which allows you to use all the advantages of OOP and also has **full support for Nette 3**.

This package automatically installs Doctrine to your project (also sets everything up in the configuration) and runs stably.

## Key Features

- Full Doctrine ORM integration with Nette Framework 3.x
- Automatic configuration and setup via DI extensions
- Advanced Tracy debug panel with SQL query profiling
- Built-in UUID and UUID Binary identifier traits
- Custom DQL functions (RAND, ROUND, GEODISTANCE, MATCH AGAINST)
- Multiple caching strategies (APCu, SQLite3, Filesystem)
- Slow query logging and analysis
- Entity inheritance support with discriminator mapping utilities
- Blue Screen exception panels for detailed error debugging

## Architecture Overview

```
+------------------+     +-------------------+     +------------------+
|   Nette DI       |---->| DatabaseExtension |---->| EntityManager    |
|   Container      |     | OrmExtension      |     | (Doctrine)       |
+------------------+     | OrmAnnotations    |     +------------------+
                         +-------------------+              |
                                  |                         v
                         +-------------------+     +------------------+
                         | ConnectionFactory |<----| DBAL Connection  |
                         +-------------------+     +------------------+
                                  |                         |
                         +-------------------+              v
                         | Cache Provider    |     +------------------+
                         | (APCu/SQLite/FS)  |     | Tracy QueryPanel |
                         +-------------------+     +------------------+
```

## Main Components

### DI Extensions

| Extension | Purpose |
|-----------|---------|
| `DatabaseExtension` | Main extension for database connection configuration |
| `OrmExtension` | Doctrine ORM configuration (proxy classes, naming strategies) |
| `OrmAnnotationsExtension` | Entity mapping and annotation reader setup |
| `OrmConsoleExtension` | CLI commands integration |
| `DbalConsoleExtension` | DBAL CLI commands |

### Core Services

| Service | Description |
|---------|-------------|
| `EntityManager` | Extended Doctrine EntityManager with fluent interface and error handling |
| `DoctrineHelper` | Utilities for entity inheritance, discriminator mapping, and type remapping |
| `Repository` | Enhanced base repository with `findPairs()` and `findByConditions()` methods |
| `ConnectionFactory` | Creates and configures DBAL connections with custom types |

### Identifier Traits

| Trait | Type | Description |
|-------|------|-------------|
| `Identifier` | `int` | Auto-increment integer ID |
| `IdentifierUnsigned` | `int` (unsigned) | Auto-increment unsigned integer ID |
| `UuidIdentifier` | `string` | UUID v4 stored as string (36 chars) |
| `UuidBinaryIdentifier` | `binary` | UUID v4 stored as binary (16 bytes) for better performance |

### Custom DQL Functions

| Function | Description |
|----------|-------------|
| `RAND()` | Random number generation |
| `ROUND(value, precision)` | Number rounding |
| `GEODISTANCE(lat1, lng1, lat2, lng2)` | Geographic distance calculation in km |
| `MATCH(column) AGAINST(value)` | MySQL fulltext search |

### Caching Providers

| Provider | Best For |
|----------|----------|
| `ApcuCache` | Production with APCu extension |
| `SQLite3Cache` | Production without APCu |
| `FilesystemCache` | Development/fallback |
| `ArrayCache` | Testing |

---

## 📦 Installation

It's best to use [Composer](https://getcomposer.org) for installation, and you can also find the package on
[Packagist](https://packagist.org/packages/baraja-core/doctrine) and
[GitHub](https://github.com/baraja-core/doctrine).

To install, simply use the command:

```shell
$ composer require baraja-core/doctrine
```

You can use the package manually by creating an instance of the internal classes, or register a DIC extension to link the services directly to the Nette Framework.

### Requirements

- PHP 8.0 or higher
- PDO extension
- Nette Framework 3.x
- Optional: APCu extension (recommended for production caching)
- Optional: SQLite3 extension (fallback caching)

---

## Configuration

A model configuration can be found in the `common.neon` file inside the root of the package.

### Basic Database Connection

In the project's `common.neon` you have to define the database credentials using the `baraja.database` extension:

```yaml
baraja.database:
    connection:
        host: 127.0.0.1
        dbname: sandbox
        user: root
        password: root
```

### Connection Options

The following connection options are available:

| Option | Type | Description |
|--------|------|-------------|
| `url` | string | DSN connection URL |
| `pdo` | string | Existing PDO instance |
| `memory` | string | In-memory database |
| `driver` | string | DBAL driver (default: `pdo_mysql`) |
| `driverClass` | string | Custom driver class |
| `driverOptions` | array | Driver-specific options |
| `unix_socket` | string | Unix socket path |
| `host` | string | Database host |
| `port` | int | Database port |
| `dbname` | string | Database name |
| `servicename` | string | Oracle service name |
| `user` | string | Username |
| `password` | string | Password |
| `charset` | string | Character set (default: `UTF8`) |
| `portability` | int | Portability mode |
| `fetchCase` | int | Fetch case mode |
| `persistent` | bool | Persistent connection |
| `types` | array | Custom DBAL types |
| `typesMapping` | array | Type mappings |
| `wrapperClass` | string | Custom connection wrapper |
| `serverVersion` | string | Server version hint |

### Environment Variable Support

The package supports the `DB_URI` environment variable for connection configuration:

```
DB_URI=mysql://user:password@host:3306/dbname
```

Additionally, you can use `DB_NAME` to override the database name from the URI.

---

## ⚙️ Drivers

In default settings Doctrine uses the `MySql` driver.

### PostgreSQL

You can switch to PostgreSQL by specifying the driver class:

```yaml
baraja.database:
    connection:
        driverClass: Doctrine\DBAL\Driver\PDO\PgSQL\Driver
```

### Other Supported Drivers

- `pdo_mysql` - MySQL (default)
- `pdo_pgsql` - PostgreSQL
- `pdo_sqlite` - SQLite
- `pdo_sqlsrv` - Microsoft SQL Server
- `pdo_oci` - Oracle

---

## Entity Mapping

In order for Doctrine to know which classes are **entities** and which **application logic**, it is necessary to set up a mapping.

### Configuration

For mapping, it is necessary to set the introductory part of the namespace entities and the directory where they occur:

```yaml
orm.annotations:
    paths:
        App\Baraja\Entity: %rootDir%/app/model/Entity
```

### Excluding Paths

You can exclude specific directories from scanning:

```yaml
orm.annotations:
    excludePaths:
        - %rootDir%/app/model/Entity/Deprecated
```

### Ignoring Annotations

Custom annotations can be ignored:

```yaml
orm.annotations:
    ignore:
        - myCustomAnnotation
```

### Cache Configuration

Configure the annotation cache driver:

```yaml
orm.annotations:
    defaultCache: filesystem  # Options: apcu, array, filesystem
    debug: false              # Enable for development
```

> **Important warning:**
>
> The value of the `%rootDir%`, `%appDir%`, `%wwwDir%`, `%vendorDir%` and `%tempDir%` parameters may be corrupted when running schema generation in CLI mode.
> To resolve this mistake, please install [Package Manager](https://github.com/baraja-core/package-manager) and call the command as a `composer dump`.

---

## Entity Identifiers

The package provides several traits for entity identification. Insert one trait to define the ID in your entities:

### Auto-increment Integer

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Baraja\Doctrine\Identifier\Identifier;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Article
{
    use Identifier;

    #[ORM\Column(type: 'string')]
    private string $title;
}
```

### UUID (String)

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User
{
    use UuidIdentifier;

    #[ORM\Column(type: 'string')]
    private string $email;
}
```

### UUID Binary (High Performance)

For better performance, use binary UUID storage:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Baraja\Doctrine\UUID\UuidBinaryIdentifier;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Product
{
    use UuidBinaryIdentifier;

    #[ORM\Column(type: 'string')]
    private string $name;
}
```

UUID will be generated automatically in PHP using the Ramsey UUID library.

> **TIP:** Read more about [UUID binary performance](https://php.baraja.cz/uuid-performance) (Czech language)

---

## Generate Database Structure from Entities

This package implements a bridge to automatically execute Doctrine commands.

### Update Schema

```shell
php www/index.php o:s:u -f --dump-sql
```

The command `o:s:u` means `orm:schema-tool:update`.

Options:
- `-f` or `--force` - Execute changes in SQL
- `--dump-sql` - Renders the list of SQL commands that will be executed

### Validate Schema

```shell
php www/index.php o:v
```

### Available Commands

| Command | Description |
|---------|-------------|
| `orm:schema-tool:create` | Create database schema |
| `orm:schema-tool:update` | Update database schema |
| `orm:schema-tool:drop` | Drop database schema |
| `orm:validate-schema` | Validate entity mappings |
| `orm:info` | Show basic information about all mapped entities |
| `dbal:run-sql` | Execute arbitrary SQL directly |

If everything works fine, the command will create the table `core__database_slow_query` which is defined in this package and is ready for logging slow queries.

> **TIP:** If you are using [Package Manager](https://github.com/baraja-core/package-manager), you can simply call the `composer dump` command.

---

## Working with EntityManager

The package provides an enhanced `EntityManager` with fluent interface:

```php
<?php

use Baraja\Doctrine\EntityManager;

class UserService
{
    public function __construct(
        private EntityManager $entityManager,
    ) {
    }

    public function createUser(string $email): User
    {
        $user = new User;
        $user->setEmail($email);

        $this->entityManager
            ->persist($user)
            ->flush();

        return $user;
    }

    public function findUser(int $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }
}
```

### Enhanced Methods

The `EntityManager` provides better error handling and returns `$this` for fluent chaining:

```php
$entityManager
    ->persist($entity1)
    ->persist($entity2)
    ->flush();
```

### Event Listeners

Add event listeners directly:

```php
$entityManager->addEventListener(['postPersist'], new MyEventListener());
```

---

## Repository

The package includes an enhanced `Repository` class with additional methods:

### findPairs()

Get key-value pairs for select boxes:

```php
$repository = $entityManager->getRepository(Category::class);

// Returns ['1' => 'Electronics', '2' => 'Books', ...]
$pairs = $repository->findPairs('name');

// With custom key column
$pairs = $repository->findPairs('name', 'slug');
```

### findByConditions()

Get values with custom conditions:

```php
$repository->findByConditions('id', [
    'e.active = 1',
    'e.createdAt > :date',
]);
```

---

## DoctrineHelper

The `DoctrineHelper` service provides utilities for working with entity inheritance and metadata:

```php
<?php

use Baraja\Doctrine\DoctrineHelper;

class ProductService
{
    public function __construct(
        private DoctrineHelper $doctrineHelper,
    ) {
    }

    public function getProductVariants(string $productClass): array
    {
        // Get all discriminator variants of an entity
        return $this->doctrineHelper->getEntityVariants($productClass);
    }

    public function getTableName(string $entityClass): string
    {
        // Get real database table name
        return $this->doctrineHelper->getTableNameByEntity($entityClass);
    }

    public function upgradeProductType(Product $product): ?SpecialProduct
    {
        // Remap entity to a more specific type
        return $this->doctrineHelper->remapEntityToBestType($product);
    }
}
```

### Available Methods

| Method | Description |
|--------|-------------|
| `getEntityVariants()` | Get all discriminator map variants |
| `getBestOfType()` | Get the most embedded (deepest) entity type |
| `getTableNameByEntity()` | Get database table name from entity class |
| `getRootEntityName()` | Get root entity class in inheritance chain |
| `getDiscriminatorByEntity()` | Get discriminator value for entity |
| `remapEntityToBestType()` | Elevate entity to best available type |
| `remapEntity()` | Remap entity from one type to another |

---

## Custom DQL Functions

### RAND()

Generate random numbers for sorting:

```php
$query = $entityManager->createQuery('
    SELECT e FROM App\Entity\Product e
    ORDER BY RAND()
');
```

### ROUND()

Round numeric values:

```php
$query = $entityManager->createQuery('
    SELECT e, ROUND(e.price, 2) as roundedPrice
    FROM App\Entity\Product e
');
```

### GEODISTANCE()

Calculate geographic distance between two points (in kilometers):

```php
$query = $entityManager->createQuery('
    SELECT e, GEODISTANCE(e.latitude, e.longitude, :lat, :lng) as distance
    FROM App\Entity\Store e
    ORDER BY distance ASC
')
    ->setParameter('lat', 50.0755)
    ->setParameter('lng', 14.4378);
```

### MATCH AGAINST

MySQL fulltext search:

```php
$query = $entityManager->createQuery('
    SELECT e
    FROM App\Entity\Article e
    WHERE MATCH(e.title, e.content) AGAINST(:search) > 0
')
    ->setParameter('search', 'search term');
```

### Registering Custom Functions

Add your own custom functions:

```php
use Baraja\Doctrine\DatabaseExtension;

DatabaseExtension::addCustomNumericFunction('MY_FUNC', MyFunction::class);
```

---

## Custom Types

### Registering Custom Types

Add custom DBAL types:

```php
use Baraja\Doctrine\DatabaseExtension;

DatabaseExtension::addCustomType('my_type', MyType::class);
```

### Via Configuration

```yaml
baraja.database:
    types:
        my_type: App\Doctrine\Type\MyType
```

### Built-in Types

| Type | Class |
|------|-------|
| `uuid` | `UuidType` - UUID as string |
| `uuid-binary` | `UuidBinaryType` - UUID as binary |

---

## Caching

The package automatically selects the best available cache:

1. **APCu** (preferred for production)
2. **SQLite3** (fallback)
3. **Filesystem** (development)

### Manual Cache Configuration

```yaml
baraja.database:
    cache: apcu  # Options: apcu, sqlite, or custom class
```

### Custom Cache Class

```yaml
baraja.database:
    cache: App\Cache\MyCustomCache
```

---

## 🐛 Tracy Debug Panel

This package contains the most advanced native tools for debugging your application and SQL queries.

![Baraja Doctrine debug Tracy panel](doc/tracy-panel-design.png)

### Features

- View all performed SQL queries
- Click directly on the place of query invocation in IDE
- Watch time graphs on the output
- Analyze slow queries
- Write query types displayed separately (SELECT, INSERT, UPDATE, DELETE)
- Transaction highlighting
- Color-coded query duration

### Duration Color Coding

| Duration | Color |
|----------|-------|
| < 5 ms | Default |
| 5-15 ms | Light orange |
| 15-75 ms | Orange |
| 75-150 ms | Dark orange |
| 150-300 ms | Light red |
| 300-500 ms | Red |
| > 500 ms | Dark red |

### Blue Screen Integration

The package includes advanced logic for debugging corrupted entities and queries directly through Tracy Bluescreen:

- **Driver Exception** - Shows SQL query, parameters, and connection troubleshooting
- **Query Exception** - Displays available fields when accessing non-existent columns
- **Mapping Exception** - Shows entity file location and annotations

---

## Slow Query Logging

Slow queries are automatically logged to the `core__database_slow_query` table:

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Unique identifier |
| `query` | text | The SQL query |
| `duration` | float | Execution time in ms |
| `hash` | string | Query hash for grouping |
| `inserted_date` | datetime | When the query was logged |

---

## 🚀 Performance Best Practices

When Doctrine is used poorly, it can be unnecessarily slow.

For more details (in Czech language): https://ondrej.mirtes.cz/doctrine-2-neni-pomala

### Automatic Optimizations

This package uses best-practices to increase the performance:

1. **Proxy Classes** - `autoGenerateProxyClasses` is set to `false` for production
2. **Metadata Caching** - Automatic APCu/SQLite3 caching
3. **Query Caching** - DQL query parsing is cached
4. **Result Caching** - Configurable result cache

### Manual Optimizations

For maximum performance, consider using Redis:

```php
// See: https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/caching.html
```

### UUID Binary Performance

Using `UuidBinaryIdentifier` instead of `UuidIdentifier` provides:
- 50% less storage space (16 bytes vs 36 characters)
- Faster indexing and lookups
- Better cache efficiency

---

## ORM Configuration

Advanced ORM settings via `orm` extension:

```yaml
orm:
    proxyDir: %tempDir%/proxies
    proxyNamespace: App\Proxy
    autoGenerateProxyClasses: false
    namingStrategy: Doctrine\ORM\Mapping\UnderscoreNamingStrategy
    defaultRepositoryClassName: App\Repository\BaseRepository
    entityNamespaces:
        App: App\Entity
```

### Available Options

| Option | Default | Description |
|--------|---------|-------------|
| `configurationClass` | `Configuration` | Custom configuration class |
| `entityManagerClass` | `EntityManager` | Custom EntityManager class |
| `proxyDir` | `%tempDir%/proxies` | Proxy classes directory |
| `autoGenerateProxyClasses` | `null` | Auto-generate proxy classes |
| `proxyNamespace` | `Baraja\Doctrine\Proxy` | Proxy classes namespace |
| `metadataDriverImpl` | `null` | Custom metadata driver |
| `entityNamespaces` | `[]` | Entity namespace aliases |
| `customStringFunctions` | `[]` | Custom DQL string functions |
| `customNumericFunctions` | `[]` | Custom DQL numeric functions |
| `customDatetimeFunctions` | `[]` | Custom DQL datetime functions |
| `customHydrationModes` | `[]` | Custom hydration modes |
| `classMetadataFactoryName` | `null` | Custom metadata factory |
| `defaultRepositoryClassName` | `null` | Default repository class |
| `namingStrategy` | `UnderscoreNamingStrategy` | Column naming strategy |
| `quoteStrategy` | `null` | Quote strategy |
| `entityListenerResolver` | `null` | Entity listener resolver |
| `repositoryFactory` | `null` | Custom repository factory |
| `defaultQueryHints` | `[]` | Default query hints |

---

## Event Subscribers

Register Doctrine event subscribers via DI:

```yaml
services:
    - App\Subscriber\TimestampSubscriber:
        tags: [doctrine.subscriber]
```

```php
<?php

namespace App\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;

class TimestampSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [Events::prePersist, Events::preUpdate];
    }

    public function prePersist($args): void
    {
        // Set created timestamp
    }

    public function preUpdate($args): void
    {
        // Set updated timestamp
    }
}
```

---

## Ignored Annotations

Configure globally ignored annotation names:

```yaml
baraja.database:
    propertyIgnoreAnnotations:
        - sample
        - endpointName
        - editable
        - myCustomAnnotation
```

---

## Troubleshooting

### Connection Refused

Verify your database is running and connection details are correct:
- Check `localhost` vs `127.0.0.1`
- Verify port number (MySQL default: 3306)
- Check firewall settings

### Authentication Method Unknown

For MySQL 8.0+, you may need to change the authentication method:

```sql
ALTER USER 'myuser' IDENTIFIED WITH mysql_native_password BY 'mypassword';
```

### DigitalOcean Managed Database

When using DigitalOcean managed databases:
- Port must be explicitly defined
- Verify your IP is allowed in the firewall

### Schema Out of Sync

Run schema update:

```shell
php www/index.php o:s:u -f
```

---

## Full Configuration Example

```yaml
extensions:
    dbal.console: Baraja\Doctrine\DBAL\DI\DbalConsoleExtension
    orm: Baraja\Doctrine\ORM\DI\OrmExtension
    orm.console: Baraja\Doctrine\ORM\DI\OrmConsoleExtension
    orm.annotations: Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension
    baraja.database: Baraja\Doctrine\DatabaseExtension

baraja.database:
    connection:
        host: %database.host%
        dbname: %database.dbname%
        user: %database.user%
        password: %database.password%
        charset: UTF8
    cache: apcu
    propertyIgnoreAnnotations:
        - sample

orm:
    proxyDir: %tempDir%/proxies
    namingStrategy: Doctrine\ORM\Mapping\UnderscoreNamingStrategy

orm.annotations:
    paths:
        App\Entity: %appDir%/model/Entity
    defaultCache: filesystem
    debug: %debugMode%
```

---

## Author

**Jan Barasek** - https://baraja.cz

---

## 📄 License

`baraja-core/doctrine` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/doctrine/blob/master/LICENSE) file for more details.
