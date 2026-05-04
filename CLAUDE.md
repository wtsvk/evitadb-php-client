# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer check                                        # run all checks (phpcs + phpstan + phpunit)
composer phpcs                                        # check code style (PSR-12 + Slevomat rules)
composer phpcbf                                       # auto-fix code style violations
composer phpstan                                      # static analysis (level 10, strict-rules, bleedingEdge)
composer phpunit                                      # run unit tests
composer phpunit -- --filter testMethodName            # run a single test

bash scripts/sync-protos.sh                           # pull .proto files from pinned EvitaDB Docker image
bash scripts/build-proto.sh                           # regenerate src/Protocol/* PHP stubs from proto/*
```

CI (`.github/workflows/ci.yml`) runs phpcs, phpstan, and phpunit on PHP 8.5 with `grpc` + `protobuf` extensions — all via `composer` scripts. A git pre-commit hook (`scripts/pre-commit.sh`) runs the same `composer check` locally before each commit; it's auto-installed via `composer.json` `post-autoload-dump` (triggers on both `composer install` and `composer update`). PHPUnit, PHPStan, and PHP_CodeSniffer are all configured to **exclude `src/Protocol`**.

System requirements for local work: PHP 8.5+, `ext-grpc` (`pecl install grpc`), `ext-protobuf` (`pecl install protobuf`). Proto regen additionally needs `protoc` and `grpc_php_plugin` on `PATH`, plus `docker`, `unzip`, `awk`, and `jq` for the sync script.

## Architecture

This is a thin PHP gRPC client for EvitaDB. The library is hand-written domain code plus a large generated tree:

- **`src/EvitaDbClientInterface.php`** — the contract consumers type-hint against. Enables mocking + dependency injection without depending on the concrete implementation.
- **`src/EvitaDbClient.php`** — the concrete implementation. Receives `EvitaServiceClient` and `EvitaSessionServiceClient` via constructor injection (pure DI). A static `EvitaDbClient::create(host, port, grpcOpts)` factory is provided for convenience when no DI container is available. Single-call methods (`query`, `getEntity`, `findEntity`, `upsertEntity`, `defineEntitySchema`, `deleteEntity`) each open a fresh gRPC session, delegate the actual operation to a `SessionScopedContext`, and close the session — in `try/catch` for `transaction()` (commit on success, discard-style close on exception) or `try/finally` for `readTransaction()` (no commit either way). For batch ops or multi-call consistent reads, consumers use `transaction(catalog, fn, dryRun=false)` / `readTransaction(catalog, fn)` directly — these pass a typed `WriteTransactionContext` / `ReadTransactionContext` to the callable, sharing one underlying session. Stateless, Octane/RoadRunner-compatible. Errors are thrown (`EvitaDbConnectionException` for transport failures, `EvitaDbStatusException` for non-OK gRPC status, `EvitaDbEntityNotFoundException` for missing entity via `getEntity`); `closeSession` intentionally swallows exceptions because it runs in `finally`.
- **`src/Transaction/`** — `ReadTransactionContext` (read-only ops) and `WriteTransactionContext extends ReadTransactionContext` (adds mutations) interfaces. `SessionScopedContext` is the concrete impl bound to one session — every method passes `['sessionid' => [$sessionId]]` as gRPC metadata. EvitaDB does NOT support runtime rollback at session close; `transaction()` on exception still closes (with weakest commit behavior) and pending mutations may persist. `dryRun: true` opens the session with the `dryRun` flag — server discards all changes at close regardless. There's no per-call abort path.
- **`src/QueryBuilder.php`** — fluent builder that emits an EvitaQL string + positional `GrpcQueryParam` list, packaged into a `GrpcQueryRequest`. The string is assembled by concatenation (filter parts joined into `filterBy(...)`, fixed `entityFetch(...)` + `page(n, size)` in `require(...)`). `withLocale` becomes `entityLocaleEquals(...)` inside `filterBy`. Values flow through positional `?` placeholders, never inlined.
- **`src/Testing/EvitaDbMockClient.php`** — in-memory fake of `EvitaDbClientInterface` shipped with the package for consumer-side unit tests. Combines stub (`->withEntity()`, `->onQuery()`), spy (records `->upsertCalls`, `->deleteCalls`, `->definedCatalogs`, `->definedEntitySchemas`) and fake (in-memory entity store) facets. Operates strictly: any call without matching stub throws — fail loud, not silent. `MockSessionScopedContext` mirrors the production transaction context but routes back to the mock client; `dryRun=true` skips recording mutations but still returns simulated PKs from `nextPrimaryKey++` (matches real EvitaDB which assigns PKs even in dryRun, just discards them at close). Public spy DTOs: `MockedUpsert`, `MockedDelete`, `MockedSchemaDefinition`. `MockedQueryStub` is `@internal`. Mock's `upsertEntity` narrows return type to `int` (covariant) since auto-incrementing PK never returns null.
- **`src/SessionType.php`** / **`src/SessionCommitBehavior.php`** — internal enums expressing session flavor (ReadOnly/ReadWrite) and close behavior (Commit/Discard) without ambiguous booleans or strings.
- **`src/Exception/`** — four-class hierarchy: abstract base `EvitaDbException` extends `RuntimeException`; concrete subclasses are `EvitaDbConnectionException` (transport failures), `EvitaDbStatusException` (non-OK gRPC status), and `EvitaDbEntityNotFoundException` (returned by `getEntity` when not found; `findEntity` returns `null` instead).
- **`src/Protocol/`** — auto-generated from `proto/*.proto` by `scripts/build-proto.sh`. **Never hand-edit these files** — they get blown away on regen. PSR-4 maps `Wtsvk\EvitaDbClient\Protocol\` here.
- **`proto/`** — committed `.proto` files synced from the EvitaDB JAR by `scripts/sync-protos.sh`.

### Proto regeneration pipeline

The pipeline has two stages, both with non-obvious patches:

1. **`scripts/sync-protos.sh`** pulls the EvitaDB Docker image at the version pinned in `composer.json` `extra.evitadb-version`, extracts only `Grpc*.proto` from `/evita/bin/evita-server.jar` (skipping transitive `google/*` and `grpc/*` deps), and idempotently injects two `option` lines next to the existing `csharp_namespace`:
   ```proto
   option php_namespace = "Wtsvk\\EvitaDbClient\\Protocol";
   option php_metadata_namespace = "Wtsvk\\EvitaDbClient\\Protocol\\GPBMetadata";
   ```
2. **`scripts/build-proto.sh`** runs `protoc` with `grpc_php_plugin`, then does two cleanup steps:
   - **Renames `Close` → `CloseSession`** in `EvitaSessionServiceClient.php`. PHP method names are case-insensitive, and `Grpc\BaseStub::close()` already exists, so the generated `Close()` RPC method collides at class-load time. This is why `EvitaDbClient::closeSession()` calls `$this->sessionService->CloseSession(...)` rather than `Close(...)`.
   - **Flattens `src/Wtsvk/EvitaDbClient/Protocol/` to `src/Protocol/`**. `protoc` writes generated files at the path implied by `php_namespace`, but our PSR-4 maps `Wtsvk\EvitaDbClient\` → `src/`, so the tree must be moved up.

If either patch is dropped from the regen scripts, the autoloader will either fail to find generated classes or hit the `Close()` collision at instantiation.

### Version sync

`composer.json` `extra.evitadb-version` is the source of truth for the targeted EvitaDB version. The package versions itself independently of EvitaDB; the README's compatibility table records which package version targets which EvitaDB version. `.github/workflows/auto-update-evitadb.yml` polls Docker Hub daily, and when it finds a new EvitaDB tag it bumps `extra.evitadb-version`, runs both regen scripts, and opens a PR.

## Conventions worth knowing

### Static analysis & code style

The project uses a strict toolchain — all checks must pass in CI before merge:

- **PHPStan** at level 10 with `phpstan-strict-rules` and `bleedingEdge.neon`. This means: no mixed types, no unsafe comparisons, strict return types, narrowed union types. If PHPStan complains, fix it — do not add `@phpstan-ignore` unless you document why.
- **PHP_CodeSniffer** with PSR-12 baseline + `slevomat/coding-standard` sniffs (configured in `phpcs.xml`). Run `composer phpcbf` to auto-fix what it can.
- **Post-edit check (mandatory after every PHP change):** run `composer phpcbf && composer phpstan` and fix all errors before finishing.

### Project-specific

- `declare(strict_types=1);` and `final` on public classes are the default. `webmozart/assert` is used to narrow generated-class types after gRPC calls (the stubs return `\Google\Protobuf\Internal\Message`).
- The client never logs — exceptions are thrown so the caller decides how to surface failures.
- Generated `src/Protocol` is excluded from PHPStan (`phpstan.neon`), PHP_CodeSniffer (`phpcs.xml`), and PHPUnit coverage (`phpunit.xml`); don't try to make those tools happy with it.

### Dependency injection & testability

- **Constructors receive ready-made dependencies** — never `new` a collaborator inside a constructor. The constructor's job is to accept dependencies, not to create them.
- **Interfaces for public-facing classes** — every class consumers type-hint against has an interface (e.g. `EvitaDbClientInterface`). This enables mocking in consumer tests and allows swapping implementations.
- **Static `create()` factory for convenience** — when a class has non-trivial wiring (connection setup, default options), provide a named constructor `::create(...)` that assembles dependencies and returns the instance. The real constructor stays clean and injectable.
- **No reflection in tests** — if a class is hard to test without reflection, that's a design smell. Fix the design (inject dependencies, extract collaborators) rather than reaching for `ReflectionClass`.

### PHP language usage

- PHP 8.5+ types fully utilized; explicit return type declarations on every method/function; explicit type hints on every parameter. Never use `mixed` when a narrower type is possible.
- PHP 8 constructor property promotion in `__construct()`; no empty `__construct()` methods unless `private`.
- `readonly` on constructor properties where the value is never mutated after construction.
- Always use curly braces for control structures, even single-line bodies.
- Strict comparisons only (`===`, `!==`). Never use `==` or `!=`.
- Prefer PHPDoc blocks over inline `//` comments. Inline comments only for genuinely non-obvious logic.
- No unused imports, variables, or parameters. PHPStan strict-rules and Slevomat sniffs enforce this automatically.
- Avoid deprecated imports — use the current alternative.

### Type precision

The goal is **maximum type specificity** — never settle for a generic type when a precise one is expressible:

- **`list<T>` over `T[]`** when the array is a sequential list (integer-indexed, no gaps). Use `T[]` only for associative arrays or when keys matter. Prefer `array<string, T>` for string-keyed maps.
- **Array shapes over generic arrays**: use `array{host: string, port: int}` instead of `array<string, string|int>`. Define complex shapes via `@phpstan-type` on the producing class; consumers import via `@phpstan-import-type`.
- **`@phpstan-type`** for any non-trivial data structure (config arrays, response shapes, DTOs passed as arrays). Name the type descriptively (`SessionConfig`, `QueryResultRow`). This removes the need for inline `@var` casts.
- **`@property` for inherited/magic properties**: when a parent class or trait exposes a property with a generic type (e.g. `mixed`, `object`), add `@property` on the child class to narrow the type to the actual concrete type.
- **`@template` generics** on interfaces and methods that operate on variable types. `transaction()` / `readTransaction()` use this for the callable's return type.
- **No lazy `mixed`**: if you know the type at the call site, annotate it. If a library returns `mixed`, narrow it immediately with `Assert::isInstanceOf()` or a type check and document the narrowed type.

### wtSVK coding style

- **Named arguments** when (a) a call has more than one argument, OR (b) any argument is placed on its own line.
- **Multi-line calls**: 3+ arguments must break onto multiple lines (one per line, trailing comma). Two-arg calls may stay inline if they fit.
- **Nested function calls**: when an argument is itself a non-trivial call (method, chained call, closure), break the outer call into multiple lines with named arguments.
- **Inline trivial single-use variables**: if a variable is obtained by a trivial call and used only once immediately after, inline it. Don't inline when the name adds clarity or the call is non-trivial.
- **Multi-line `if` conditions**: when a condition has multiple non-trivial sub-expressions, break each onto its own line with the logical operator at the start of the line:
  ```php
  if (
      $this->foo->isActive()
      && $bar->getCount() > 0
      && ! $baz->isDeleted()
  ) {
  ```
- **`json_encode` / `json_decode`**: always pass `JSON_THROW_ON_ERROR`, use named arguments, each argument on its own line.
- **Intersection types**: separate `&` with spaces — `Foo & Bar`, not `Foo&Bar`.
- **`get` vs `find` prefix**: `get*` always returns the object (throws if missing). `find*` may return `null`. Don't suffix with `OrNull`.
- **Webmozart assertions**: `Webmozart\Assert\Assert::*` for guard clauses and runtime narrowing — never hand-rolled `if (! is_string($x)) throw ...` chains.
- **Closed set of values → enum or class constants**: never use a free `string` where only a fixed set is valid. Backed enums serialize automatically via `json_encode` — `->value` only when comparing against a raw decoded string.
- **Factory over direct `new`** when a type is constructed in multiple places. Direct `new` is fine for one-off local objects.
- **No layer just for reshuffling**: a class/DTO that only moves data A → B without its own logic adds no value. Solve it in the existing consumer.
- **Extract non-trivial callbacks**: closures with more than ~3 lines passed to `array_map`, stream callbacks, etc., must be extracted to private methods and passed via first-class callable syntax (`$this->methodName(...)`). Inline only truly trivial one-liners.
- **Early `continue` over nesting** in `foreach` loops.
- **Mutually exclusive type branches**: prefer `if`/`elseif` chains for `instanceof` checks over separate `if` blocks with early `return`.
- **PHPDoc generics / array shapes**: see "Type precision" section above.
- **`#[Override]`** in tests (and elsewhere) when overriding parent methods.
