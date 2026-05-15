# Changelog

## [0.4.2] — 2026-05-15 — Auto go-live on `defineCatalog()`

### API tweaks

- **`EvitaDbConnection::defineCatalog()` now auto-transitions to `ALIVE`** — on successful creation the connection opens a temporary read-write session and calls `GoLiveAndClose` so the catalog is immediately usable for transactional reads/writes. Previously the catalog was left in `WARMING_UP` state and consumers had to issue the go-live RPC themselves. If the catalog already existed (`success=false`), go-live is skipped. The fast bulk-load `WARMING_UP` write path is not exposed by this client — callers always end up with an `ALIVE` catalog.
- **`MockEvitaDbConnection::defineCatalog()` mirrors the new contract** — returns `false` on the second call with the same name (simulates the "already existed" branch).
- **Interface PHPDoc on `EvitaDbConnectionInterface::defineCatalog()`** documents the new behavior.

## [0.4.1] — 2026-05-11 — SAFE mode query fix

### Bug fixes

- **QueryBuilder emits fully parameterized EvitaQL** — all inline literals (single-quoted strings and numeric values) replaced with `?` positional placeholders. Previously every query produced by `QueryBuilder::build()` was rejected by EvitaDB's SAFE-mode `Query` RPC with _"Literal value is forbidden in mode SAFE"_. Fixes [#5](https://github.com/wtsvk/evitadb-php-client/issues/5).
  - `collection(?)`, `entityLocaleEquals(?)`, `page(?, ?)` — header/pagination literals parameterized in `build()`.
  - All filter methods (`filterByAttribute`, `filterByAttributeContains`, `filterByAttributeGreaterThan`, `filterByAttributeLessThan`, `filterByAttributeBetween`, `filterByAttributeStartsWith`, `filterByAttributeInSet`, `filterByReferencePrimaryKeyInSet`) — attribute/reference names now emitted as `?` with a positional string param.
  - `orderByAttributeNatural` — attribute name parameterized; `ASC`/`DESC` remain as EvitaQL keywords (not literals).
  - `EntityFetch::toEvitaQL()` — all attribute, reference, associated data, and locale names emitted as `?` placeholders. New `getParams()` method exposes collected `GrpcQueryParam` list.
  - `SessionScopedReads::fetchEntity()` — now passes `EntityFetch` params to `GrpcEntityRequest::setPositionalQueryParams()`.
- Positional param ordering: header (collection + locale) → filter values → order names → entity fetch names → page numbers. Matches left-to-right `?` order in the rendered EvitaQL string.

### New

- **`EntityFetch::getParams()`** (`@internal`) — returns the `list<GrpcQueryParam>` collected during the last `toEvitaQL()` call. Consumers should not call this directly; it is used internally by `QueryBuilder::build()` and `SessionScopedReads::fetchEntity()`.
- **`EntityFetchTest`** — new unit test class (13 tests) covering parameterized output, param collection, re-render reset, and validation.
- **`QueryBuilderTest::testNoInlineLiteralsInGeneratedQuery`** — asserts no single/double quotes or bare integers remain in the generated EvitaQL string.
- **`QueryBuilderTest::testPositionalParamOrderMatchesPlaceholders`** — verifies exact param order across all query sections.

## [0.4.0] — 2026-05-06 — Major refactoring

### Breaking changes

- **`EvitaDbClient` is now transaction-only** — all single-call methods (`query()`, `getEntity()`, `findEntity()`, `upsert()`, `upsertEntity()`, `deleteEntity()`, `defineEntitySchema()`) are removed from the client. All access goes through `writeTransaction()` / `readTransaction()`. This eliminates the silent N+1 session footgun (each single-call previously opened its own session) and removes ~70 % of the client's surface duplication.
- **`transaction()` renamed to `writeTransaction()`** — symmetric with `readTransaction()`, self-describing at call site.
- **`writeTransaction()` no longer closes the session on exception** — instead, the session is intentionally orphaned. The EvitaDB gRPC `Close()` RPC has no rollback semantics (it always commits), so the only way to discard pending mutations is to let the server's session timeout do it. For deterministic rollback, use `dryRun: true`.
- **`SessionScopedContext` split into `ReadOnlySessionScopedContext` and `ReadWriteSessionScopedContext`** — eliminates the runtime-castable leak where `readTransaction()` callers could downcast to a write context. Both implementations share read-side code via the `SessionScopedReads` trait.
- **`MockSessionScopedContext` split into `MockReadOnlySessionScopedContext` and `MockReadWriteSessionScopedContext`** — mirrors the production split.
- **New `EvitaDbConnection` class** — `isHealthy()` and `defineCatalog()` moved here from `EvitaDbClient`. New server-level operations: `getCatalogNames()`, `deleteCatalog()`.
- **`EvitaDbClient` is now catalog-scoped** — `$catalog` is a constructor parameter. `EvitaDbClient::create()` now requires `$catalog`.
- **`SessionCommitBehavior` enum completely reworked** — old `Commit`/`Discard` cases replaced with three values backed by `GrpcCommitBehavior` constants: `WaitForConflictResolution`, `WaitForLogPersistence`, `WaitForChangesVisible`.
- **`writeTransaction()` gains `$commitBehavior` parameter** — optional per-call override of the client's default commit behavior.
- **`deleteEntity()` (on transaction context) now throws `EvitaDbEntityNotFoundException`** when the entity does not exist (previously returned `true` silently).
- **New `EntityFetch` builder** replaces all raw EvitaQL `$require` strings — `getEntity()`, `findEntity()`, `upsert()`, and `QueryBuilder` all accept `?EntityFetch` instead of `?string`.
- **`QueryBuilder::filterByCategoryId()` removed** — use `filterByReferencePrimaryKeyInSet('Category', [...])` instead.
- **Mock client DTOs lose `$catalog` property** — `MockedUpsert`, `MockedDelete`, `MockedSchemaDefinition` no longer carry catalog (implicit from client instance).
- **`EvitaDbMockClient` constructor gains `$catalog` parameter** — the mock is now catalog-scoped like the real client.
- **`MockedQueryStub` loses `$catalog` property** — query stubs are no longer scoped by catalog (the mock client is already catalog-scoped).

### New features

- **`EvitaDbConnection`** — server-level class with `isHealthy()`, `defineCatalog()`, `getCatalogNames()`, `deleteCatalog()`, and `catalog()` factory for creating catalog-scoped clients.
- **`EvitaDbConnectionInterface`** — interface for `EvitaDbConnection`, enables DI and mocking.
- **`EntityFetch` builder** — immutable, fluent builder for specifying entity content requirements: `attributeContentAll()`, `attributeContent(names...)`, `associatedDataContentAll()`, `associatedDataContent(names...)`, `priceContentAll()`, `priceContentRespectingFilter()`, `referenceContentAll()`, `referenceContent(names...)`, `hierarchyContent()`, `dataInLocalesAll()`, `dataInLocales(locales...)`.
- **`SortDirection` enum** — `Asc` / `Desc` for QueryBuilder ordering.
- **`GrpcStatusHelper` trait** — eliminates duplicated `statusDetails()` / `statusCode()` methods across Connection, Client, and the session-scoped context implementations.
- **`SessionScopedReads` trait** — read-side gRPC implementations shared between `ReadOnlySessionScopedContext` and `ReadWriteSessionScopedContext` without exposing write methods through the read-only type.

### API tweaks

- **`EntityFetch` is now a mutable fluent builder** — previously each method returned a freshly cloned instance. Construct a fresh `EntityFetch` per query; do not reuse a partially-configured one across call-sites. This is consistent with how `QueryBuilder` already behaves.
- **Default content fetch is now minimal** — calling `getEntity()` / `findEntity()` / `QueryBuilder::build()` without an explicit `EntityFetch` previously embedded `EntityFetch::all()` (all attributes + associated data + prices + references). The new default omits the `entityFetch(...)` constraint entirely so the server returns identity-only entities. To preserve the old behavior, pass `EntityFetch::all()` explicitly. Saves bandwidth on PK-only queries.
- **`closeSession` failures on the success path now propagate** — `writeTransaction()` previously swallowed every close error (success or exception path), so a network blip during close silently masked an uncommitted transaction. Now the success-path close throws `EvitaDbConnectionException` / `EvitaDbStatusException` so the consumer learns. The exception path of `writeTransaction()` (orphan semantics) and the `readTransaction()` finally-path still swallow.
- **`GrpcStatusHelper` trait replaced by `GrpcStatus` value object** — typed readonly VO with `int $code`, `string $details`, and a `Stringable` `__toString()` that renders `<details> (status <code>)`. Each gRPC call site does `GrpcStatus::fromRaw($rawStatus)` once at the boundary. Trait file removed.
- **`EvitaDbConnection::isHealthy()` no longer swallows `Error`** — only `Exception` is caught. Programmer bugs (`TypeError`, `AssertionError`, ...) propagate as they should; transport / server-misbehavior still produces `false`.
- **`EvitaDbClient::create()` delegates to `EvitaDbConnection::create()->catalog()`** — keepalive defaults live in one place.
- **`MockEvitaDbConnection`** — in-memory fake of `EvitaDbConnectionInterface` for testing.
- **New QueryBuilder methods** — `filterByAttributeGreaterThan()`, `filterByAttributeLessThan()`, `filterByAttributeBetween()`, `filterByAttributeStartsWith()`, `filterByAttributeInSet()`, `filterByEntityPrimaryKeyInSet()`, `filterByReferencePrimaryKeyInSet()`, `filterPriceValidIn()`, `orderByAttributeNatural()`, `orderByPriceNatural()`, `withEntityFetch()`.
- **New transaction context methods** — `getCatalogSchema()`, `getEntitySchema()`, `getAllEntityTypes()`, `getEntityCollectionSize()`, `deleteEntities()`, `updateEntitySchema()`.

### Bug fixes

- **Rollback-on-exception semantics corrected** — `writeTransaction()` previously called `Close()` on exception with the fastest commit behavior, which silently *committed* all pending mutations (the gRPC `Close()` RPC has no rollback flag). The session is now intentionally left open on exception so the server-side timeout discards the buffered transaction.
- **Read/write transaction context type leak closed** — splitting `SessionScopedContext` into two concrete classes prevents `readTransaction()` callers from runtime-casting to `WriteTransactionContext` and issuing mutations on a read-only session.
- **Mock upsert-response parity with production** — `MockReadWriteSessionScopedContext::upsertEntity()` now handles all three response branches (`hasEntity`, `hasEntityReference`, `hasEntityReferenceWithAssignedPrimaryKeys`) like production does.
- **Float-to-string precision** — `QueryBuilder` now uses `number_format()` instead of `(string)` cast, avoiding scientific notation for very small/large floats.
- **EvitaQL injection prevention** — all identifier names in `QueryBuilder` and `EntityFetch` are validated against `[a-zA-Z][a-zA-Z0-9_]*`.
- **`deleteEntity()` response parsing** — now checks `GrpcDeleteEntityResponse` for entity reference/entity presence; throws `EvitaDbEntityNotFoundException` when the response is empty (entity didn't exist).
- **Commit behavior semantics** — corrected from misleading "Commit/Discard" (which implied rollback) to accurate "WaitFor*" names that reflect what EvitaDB actually does (all three commit; the difference is how long close blocks).
