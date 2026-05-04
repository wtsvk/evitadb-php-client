# Changelog

All notable changes to this package will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `EvitaDbClient::transaction()` and `EvitaDbClient::readTransaction()` — explicit transaction-scoped APIs that pass a typed `WriteTransactionContext` / `ReadTransactionContext` to the callable. Multiple operations in one callable share a single underlying EvitaDB session, eliminating per-call session overhead and giving reads a consistent snapshot.
- `EvitaDbClient::findEntity()` — companion to `getEntity()` that returns `null` instead of throwing `EvitaDbEntityNotFoundException` when the entity does not exist. Use this when "missing" is an expected outcome rather than an error.
- `transaction()` accepts an optional `bool $dryRun = false` parameter. When `true`, the underlying session is opened with EvitaDB's `dryRun` flag — all mutations are discarded server-side at session close regardless of whether the callable returned normally. Useful for migration dry-runs and integration tests.
- `Wtsvk\EvitaDbClient\Testing\EvitaDbMockClient` — in-memory fake of `EvitaDbClientInterface` for testing consumer applications without spinning up a real EvitaDB instance. Combines stub (`->withEntity()`, `->onQuery()`), spy (records `->upsertCalls`, `->deleteCalls`, `->definedCatalogs`, `->definedEntitySchemas`) and fake (in-memory entity store) facets. Operates strictly: any call without a matching stub throws so misconfigurations fail loud.

  ```php
  use Wtsvk\EvitaDbClient\Testing\EvitaDbMockClient;

  $client = (new EvitaDbMockClient())
      ->withEntity(catalog: 'cat', entityType: 'Product', primaryKey: 42, entity: $stubEntity)
      ->onQuery(catalog: 'cat', matcher: $myMatcher, response: $cannedResponse);

  $service = new ProductService($client);
  $service->doSomething();

  $this->assertCount(1, $client->upsertCalls);
  ```

  `upsertEntity()` on the mock returns auto-incrementing primary keys (mirrors EvitaDB's PK assignment). Adjust the starting value via `$client->nextPrimaryKey = 1000` if your test needs specific values. Under `dryRun: true`, primary keys are still returned (matches real EvitaDB which assigns PKs but discards changes at close), but mutations are not recorded in spy lists.

### Changed

- **BREAKING**: `EvitaDbClient::withReadSession()` and `EvitaDbClient::withWriteSession()` removed. They exposed a raw session ID `string` to the callable and required consumers to construct gRPC requests manually. Replace them with `readTransaction()` / `transaction()`, which expose a typed context object with high-level methods.

  Migration:
  ```php
  // Before
  $client->withWriteSession('catalog', function (string $sessionId) use ($client) {
      // raw gRPC calls with $sessionId metadata
  });

  // After
  $client->transaction('catalog', function (WriteTransactionContext $tx) {
      $tx->upsertEntity($mutation);
      $tx->deleteEntity('Product', 42);
  });
  ```

### Notes on transaction semantics

EvitaDB does not support runtime rollback at session close. On exception inside `transaction()`'s callable the session is closed with `WAIT_FOR_CONFLICT_RESOLUTION` (fastest commit path) but pending mutations may still be applied. For guaranteed discard pass `dryRun: true`. For true atomicity in production code, design idempotent operations or apply the saga pattern in your domain layer.

## [0.2.0] — 2026-04-30

### Changed

- Bumped EvitaDB target to **2026.1.8**. Stubs regenerated from updated proto files.

## [0.1.0] — 2026-04-29

Initial release. Targets EvitaDB **2026.1.7**.

- `EvitaDbClient` with constructor injection + `EvitaDbClient::create()` factory.
- `QueryBuilder` for fluent EvitaQL construction.
- `EvitaDbClientInterface` for DI / mocking.
- `SessionType` and `SessionCommitBehavior` enums.
- Auto-update GitHub workflow tracking new EvitaDB releases.
- Publish-to-Packagist workflow with auto-tagging on `Bump EvitaDB to ...` commits and tag-push notify path.
