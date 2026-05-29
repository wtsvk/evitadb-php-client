# evitadb-php-client

PHP gRPC client for [EvitaDB](https://evitadb.io) — generated stubs + a thin session-based wrapper for managing read/write sessions.

## Installation

```bash
composer require wtsvk/evitadb-php-client
```

System requirements:

- PHP 8.5+
- `ext-grpc` (`pecl install grpc`)
- `ext-protobuf` (`pecl install protobuf`)

## Usage

The client has two layers: `EvitaDbConnection` for server-level operations, and `EvitaDbClient` for catalog-scoped data access.

### Quick start

```php
use Wtsvk\EvitaDbClient\EvitaDbClient;
use Wtsvk\EvitaDbClient\QueryBuilder;
use Wtsvk\EvitaDbClient\Transaction\ReadTransactionContext;

// Catalog-scoped client — all operations target this catalog
$client = EvitaDbClient::create(host: 'localhost', port: 5555, catalog: 'myCatalog');

$response = $client->readTransaction(function (ReadTransactionContext $tx) {
    $query = (new QueryBuilder('Product'))
        ->withLocale('sk')
        ->filterByAttribute('code', 'PROD-001')
        ->page(1, 20)
        ->build();

    return $tx->query($query);
});
```

### Connection + catalog split

```php
use Wtsvk\EvitaDbClient\EvitaDbConnection;
use Wtsvk\EvitaDbClient\Transaction\ReadTransactionContext;

$conn = EvitaDbConnection::create(host: 'localhost', port: 5555);

if (! $conn->isHealthy()) {
    throw new RuntimeException('EvitaDB unreachable');
}

// Server-level operations
$conn->defineCatalog('myCatalog'); // creates the catalog AND transitions it to ALIVE
$catalogs = $conn->getCatalogNames();

// Create catalog-scoped clients
$client = $conn->catalog('myCatalog');
$entity = $client->readTransaction(
    fn (ReadTransactionContext $tx) => $tx->getEntity(entityType: 'Product', primaryKey: 42),
);
```

For Laravel apps, register singletons in your `AppServiceProvider`:

```php
use Wtsvk\EvitaDbClient\EvitaDbConnection;
use Wtsvk\EvitaDbClient\EvitaDbConnectionInterface;
use Wtsvk\EvitaDbClient\EvitaDbClientInterface;

$this->app->singleton(EvitaDbConnectionInterface::class, fn () => EvitaDbConnection::create(
    host: config('evitadb.host'),
    port: (int) config('evitadb.port'),
));

$this->app->singleton(EvitaDbClientInterface::class, fn ($app) =>
    $app->make(EvitaDbConnectionInterface::class)->catalog(config('evitadb.catalog')),
);
```

### Transactions

All data access goes through `writeTransaction()` and `readTransaction()`. Each call opens **one** gRPC session for the duration of the callable, giving you a consistent snapshot for reads and one server-side transaction for writes.

```php
use Wtsvk\EvitaDbClient\Transaction\WriteTransactionContext;
use Wtsvk\EvitaDbClient\Transaction\ReadTransactionContext;

// Write batch — many mutations, one session
$client->writeTransaction(function (WriteTransactionContext $tx) use ($products) {
    foreach ($products as $product) {
        $tx->upsertEntity($product->toMutation());
    }
});

// Consistent read snapshot
$bundle = $client->readTransaction(function (ReadTransactionContext $tx) {
    return [
        'product' => $tx->getEntity('Product', 42),
        'category' => $tx->findEntity('Category', 7),
    ];
});

// Mixed: read existing entity then update it
$pk = $client->writeTransaction(function (WriteTransactionContext $tx) use ($newProductMutation) {
    if ($tx->findEntity('Product', 42) !== null) {
        $tx->deleteEntity('Product', 42);
    }
    return $tx->upsertEntity($newProductMutation);
});
```

`getEntity()` throws `EvitaDbEntityNotFoundException` if the entity is missing; use `findEntity()` if `null` is an acceptable outcome.

#### Rollback semantics

EvitaDB's gRPC `Close()` RPC has no rollback flag — calling it always commits. The PHP client therefore handles the two paths differently:

- **Normal return**: session is closed via `Close()` with the configured commit behavior (commits server-side).
- **Exception inside the callable**: the session is **intentionally orphaned** (no `Close()` is sent). The EvitaDB server's session timeout will discard the buffered transaction, giving you a deferred-but-real rollback. The exception propagates out of `writeTransaction()` unchanged.
- **Deterministic rollback**: pass `dryRun: true` — the server discards all changes on close regardless of outcome.

```php
$client->writeTransaction(
    fn: function (WriteTransactionContext $tx) {
        // experiment freely — nothing will persist
        $tx->upsertEntity($mutation);
    },
    dryRun: true,
);
```

### Commit behavior

Control how long the gRPC close call blocks before returning:

```php
use Wtsvk\EvitaDbClient\SessionCommitBehavior;

// Fast bulk import — don't wait for indexes
$client = EvitaDbClient::create(
    host: 'localhost',
    port: 5555,
    catalog: 'myCatalog',
    defaultCommitBehavior: SessionCommitBehavior::WaitForConflictResolution,
);

// Override per call when needed
$client->writeTransaction(
    fn: fn (WriteTransactionContext $tx) => $tx->upsertEntity($mutation),
    commitBehavior: SessionCommitBehavior::WaitForChangesVisible,
);
```

### EntityFetch — control what content is returned

By default the server returns identity-only entities (no attributes, prices, or references). Pass an `EntityFetch` to ask for specific content. Pass `EntityFetch::all()` for everything.

```php
use Wtsvk\EvitaDbClient\EntityFetch;

// Fetch only specific attributes and prices
$entity = $client->readTransaction(
    fn (ReadTransactionContext $tx) => $tx->getEntity(
        entityType: 'Product',
        primaryKey: 42,
        require: (new EntityFetch())
            ->attributeContent('name', 'code')
            ->priceContentAll(),
    ),
);

// In QueryBuilder
$query = (new QueryBuilder('Product'))
    ->withEntityFetch((new EntityFetch())->attributeContent('name')->priceContentRespectingFilter())
    ->filterByAttribute('code', 'PROD-001')
    ->build();
```

### QueryBuilder — filtering and ordering

```php
use Wtsvk\EvitaDbClient\SortDirection;

$query = (new QueryBuilder('Product'))
    ->withLocale('en')
    ->filterByAttribute('status', 'active')
    ->filterByAttributeGreaterThan('price', 10)
    ->filterByAttributeBetween('weight', 0.5, 10.0)
    ->filterByReferencePrimaryKeyInSet('Category', [1, 2, 3])
    ->filterPriceInCurrency('EUR')
    ->filterPriceInPriceLists(['retail', 'wholesale'])
    ->orderByAttributeNatural('name', SortDirection::Asc)
    ->orderByPriceNatural(SortDirection::Desc)
    ->page(1, 20)
    ->build();
```

### Testing your application

The package ships with `EvitaDbMockClient` and `MockEvitaDbConnection` for unit-testing application code:

```php
use Wtsvk\EvitaDbClient\Testing\EvitaDbMockClient;

public function testProductServiceReturnsPrice(): void
{
    // Consumer code calls $client->readTransaction(...) internally — the mock
    // routes the read through MockReadOnlySessionScopedContext, which looks up
    // the stubbed entity below.
    $client = (new EvitaDbMockClient('myCatalog'))
        ->withEntity(entityType: 'Product', primaryKey: 42, entity: $sealedEntity);

    $service = new ProductService($client);

    $this->assertSame(100, $service->getProductPrice(42));
}

public function testProductServiceUpsertsRecordsCall(): void
{
    $client = new EvitaDbMockClient('myCatalog');
    $service = new ProductService($client);

    $service->createProduct(name: 'iPhone', price: 999);

    // Consumer code calls $client->writeTransaction(fn ($tx) => $tx->upsertEntity(...))
    // — the mock records the mutation as a spy entry below.
    $this->assertCount(1, $client->upsertCalls);
}
```

The mock auto-assigns primary keys for `upsertEntity()` (start with `$client->nextPrimaryKey = 1000` to mimic existing data). Strict mode: any call without a matching stub throws — fail loud, not silent. See `tests/Unit/Testing/EvitaDbMockClientTest.php` for full API examples.

## EvitaDB version compatibility

This package follows independent semver. The targeted EvitaDB version is recorded in `composer.json` `extra.evitadb-version`. Wrapper-only fixes/refactors bump the package version (patch or minor) without changing the EvitaDB target — they don't get a row here.

Each row in the matrix below marks the **first** package version that introduced support for the listed EvitaDB version. Every later package release until the next row keeps the same EvitaDB target.

| Package | EvitaDB |
|---------|---------|
| 0.6.x   | 2026.1.11 |
| 0.5.x   | 2026.1.9 |
| 0.2.x   | 2026.1.8 |
| 0.1.x   | 2026.1.7 |

For a specific EvitaDB version, pin the package version that matches (e.g. `^0.2` if you target EvitaDB 2026.1.8).

## Architecture

- `src/EvitaDbConnectionInterface.php` / `src/EvitaDbConnection.php` — server-level operations (`isHealthy`, `defineCatalog`, `getCatalogNames`, `deleteCatalog`) and factory for catalog-scoped clients via `catalog()`.
- `src/EvitaDbClientInterface.php` / `src/EvitaDbClient.php` — catalog-scoped gRPC client with a transaction-only public surface: `writeTransaction()` and `readTransaction()`. The catalog is bound at construction. Session management, commit behavior, and rollback-on-exception (session orphaning) are all handled internally.
- `src/EntityFetch.php` — mutable fluent builder for specifying what entity content to return (attributes, prices, references, etc.). Construct fresh per query.
- `src/QueryBuilder.php` — mutable fluent builder producing EvitaQL `GrpcQueryRequest` messages. Supports filtering, ordering, pagination, and custom `EntityFetch`.
- `src/Transaction/` — `ReadTransactionContext` and `WriteTransactionContext` interfaces consumers receive inside `readTransaction()` / `writeTransaction()` callables. Concrete implementations: `ReadOnlySessionScopedContext` (read-only) and `ReadWriteSessionScopedContext` (read + write), both backed by one EvitaDB session and sharing read code via the `SessionScopedReads` trait.
- `src/SessionCommitBehavior.php` — enum controlling how long the close call blocks (`WaitForConflictResolution`, `WaitForLogPersistence`, `WaitForChangesVisible`).
- `src/SortDirection.php` — enum for query ordering (`Asc`, `Desc`).
- `src/GrpcStatus.php` — typed readonly value object wrapping the `stdClass` returned by gRPC `wait()`. Used by Connection, Client, and the session-scoped context implementations to render uniform error messages.
- `src/Testing/` — `EvitaDbMockClient`, `MockEvitaDbConnection`, mock context classes (`MockReadOnlySessionScopedContext`, `MockReadWriteSessionScopedContext`), and supporting DTOs for unit-testing consumer applications without a live EvitaDB.
- `src/Exception/` — custom exception hierarchy. The client throws on errors instead of logging — your app handles them.
- `src/Protocol/` — auto-generated PHP classes from EvitaDB `.proto` definitions. Do not edit manually.
- `proto/` — committed `.proto` source files synced from the EvitaDB Docker image.

## Maintenance scripts

- `scripts/sync-protos.sh` — pulls `.proto` files from the pinned EvitaDB image and applies the PHP namespace patches. Run when bumping EvitaDB version.
- `scripts/build-proto.sh` — runs `protoc + grpc_php_plugin` to regenerate `src/Protocol/*` from `proto/*`.

GitHub Actions automates this via `auto-update-evitadb.yml` (daily cron) which detects new EvitaDB releases and opens a PR with regenerated stubs.

## License

Apache 2.0. See `LICENSE`.

EvitaDB itself is licensed under the Business Source License 1.1, converting to Apache 2.0 on 2027-01-01. The `.proto` files in this package are interface definitions extracted from the EvitaDB JAR — derivative work that should be permissible under BSL's "make non-production use" + "modify" grants for an interop client. Consult [EvitaDB's LICENSE](https://github.com/FgForrest/evitaDB/blob/master/LICENSE) for authoritative terms.
