# Changelog

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
