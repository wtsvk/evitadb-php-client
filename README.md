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

```php
use Wtsvk\EvitaDbClient\EvitaDbClient;
use Wtsvk\EvitaDbClient\QueryBuilder;

$client = EvitaDbClient::create(host: 'localhost', port: 5555);

if (! $client->isHealthy()) {
    throw new RuntimeException('EvitaDB unreachable');
}

$query = (new QueryBuilder('Product'))
    ->withLocale('sk')
    ->filterByAttribute('code', 'PROD-001')
    ->page(1, 20)
    ->build();

$response = $client->query('catalogName', $query);
```

For Laravel apps, register a singleton in your `AppServiceProvider` (type-hint the interface for testability):

```php
use Wtsvk\EvitaDbClient\EvitaDbClient;
use Wtsvk\EvitaDbClient\EvitaDbClientInterface;

$this->app->singleton(EvitaDbClientInterface::class, fn () => EvitaDbClient::create(
    host: config('evitadb.host'),
    port: (int) config('evitadb.port'),
));
```

### Transactions and batched operations

Single-call methods like `query()` and `upsertEntity()` open and close their own gRPC session per call. For batch operations or multi-call reads that need a consistent snapshot, use `transaction()` / `readTransaction()` — both share one underlying session for the duration of the callable:

```php
use Wtsvk\EvitaDbClient\Transaction\WriteTransactionContext;
use Wtsvk\EvitaDbClient\Transaction\ReadTransactionContext;

// Write batch — many mutations, one session
$client->transaction('catalog', function (WriteTransactionContext $tx) use ($products) {
    foreach ($products as $product) {
        $tx->upsertEntity($product->toMutation());
    }
});

// Consistent read snapshot
$bundle = $client->readTransaction('catalog', function (ReadTransactionContext $tx) {
    return [
        'product' => $tx->getEntity('Product', 42),
        'category' => $tx->findEntity('Category', 7),
    ];
});

// Mixed: read existing entity then update it
$pk = $client->transaction('catalog', function (WriteTransactionContext $tx) {
    if ($tx->findEntity('Product', 42) !== null) {
        $tx->deleteEntity('Product', 42);
    }
    return $tx->upsertEntity($newProductMutation);
});
```

`getEntity()` throws `EvitaDbEntityNotFoundException` if the entity is missing; use `findEntity()` if `null` is an acceptable outcome.

> ⚠️ **EvitaDB does not support runtime rollback at session close.** On exception inside `transaction()`, pending mutations may still be persisted server-side (see [CHANGELOG](CHANGELOG.md) for details). For guaranteed discard pass `dryRun: true`; for production atomicity design idempotent operations.

### Testing your application

The package ships with `Wtsvk\EvitaDbClient\Testing\EvitaDbMockClient` — an in-memory fake of `EvitaDbClientInterface` for unit-testing application code that depends on EvitaDB. Stub canned responses, then assert recorded calls:

```php
use Wtsvk\EvitaDbClient\Testing\EvitaDbMockClient;

public function testProductServiceReturnsPrice(): void
{
    $client = (new EvitaDbMockClient())
        ->withEntity(catalog: 'cat', entityType: 'Product', primaryKey: 42, entity: $sealedEntity);

    $service = new ProductService($client);

    $this->assertSame(100, $service->getProductPrice(42));
}

public function testProductServiceUpsertsRecordsCall(): void
{
    $client = new EvitaDbMockClient();
    $service = new ProductService($client);

    $service->createProduct(name: 'iPhone', price: 999);

    $this->assertCount(1, $client->upsertCalls);
    $this->assertSame('cat', $client->upsertCalls[0]->catalog);
}
```

`upsertEntity()` on the mock auto-assigns primary keys (start with `$client->nextPrimaryKey = 1000` to mimic existing data). Strict mode: any call without a matching stub throws — fail loud, not silent. See `tests/Unit/Testing/EvitaDbMockClientTest.php` for full API examples.

## EvitaDB version compatibility

This package follows independent semver. The targeted EvitaDB version is recorded in `composer.json` `extra.evitadb-version`. Wrapper-only fixes/refactors bump the package version (patch or minor) without changing the EvitaDB target — they don't get a row here.

Each row in the matrix below marks the **first** package version that introduced support for the listed EvitaDB version. Every later package release until the next row keeps the same EvitaDB target.

| Package | EvitaDB |
|---------|---------|
| 0.2.x   | 2026.1.8 |
| 0.1.x   | 2026.1.7 |

For a specific EvitaDB version, pin the package version that matches (e.g. `^0.2` if you target EvitaDB 2026.1.8).

## Architecture

- `src/EvitaDbClientInterface.php` — the contract consumers type-hint against for DI and mocking.
- `src/EvitaDbClient.php` — session-based gRPC client wrapper. Receives dependencies via constructor injection; use `EvitaDbClient::create(host, port)` for quick setup without a DI container. Single-call methods open + close a session; `transaction()` / `readTransaction()` share one session across many calls inside the callable. Stateless, Octane-compatible.
- `src/QueryBuilder.php` — fluent builder producing EvitaQL `GrpcQueryRequest` messages.
- `src/Transaction/` — `ReadTransactionContext` and `WriteTransactionContext` interfaces consumers receive inside `readTransaction()` / `transaction()` callables, plus the `SessionScopedContext` implementation bound to a single EvitaDB session.
- `src/Testing/` — `EvitaDbMockClient` (and supporting DTOs) for unit-testing consumer applications without a live EvitaDB.
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
