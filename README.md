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

## EvitaDB version compatibility

This package follows independent semver. The targeted EvitaDB version is recorded in `composer.json` `extra.evitadb-version`. Patch fixes to the wrapper bump the package patch version without re-tagging older releases.

| Package | EvitaDB |
|---------|---------|
| 0.2.x   | 2026.1.8 |
| 0.1.x   | 2026.1.7 |

For a specific EvitaDB version, pin the package version that matches.

## Architecture

- `src/EvitaDbClientInterface.php` — the contract consumers type-hint against for DI and mocking.
- `src/EvitaDbClient.php` — session-based gRPC client wrapper. Receives dependencies via constructor injection; use `EvitaDbClient::create(host, port)` for quick setup without a DI container. Each operation opens a read or write session, executes, and closes. Stateless, Octane-compatible.
- `src/QueryBuilder.php` — fluent builder producing EvitaQL `GrpcQueryRequest` messages.
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
