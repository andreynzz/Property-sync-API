# Property Sync API

A WordPress plugin that imports and synchronizes real-estate listings from an
external REST API into a public `property` custom post type. It is a portfolio
project focused on a small, production-minded integration: authenticated HTTP,
normalization, idempotent persistence, manual and scheduled runs, locking,
structured logs, Docker, tests, and CI.

## Features

- Public `property` CPT, REST support, archive at `/properties/`, and property
  type, city, and status taxonomies.
- Authenticated, paginated REST client with validation, a 15-second timeout,
  redirect limit, and page limit.
- Canonical normalization and SHA-256 change detection: unchanged listings are
  skipped instead of updated.
- Safe create/update persistence by external ID; duplicate external IDs are
  treated as integrity errors.
- Administrator dashboard for API settings, a masked token, last-run summary,
  recent activity, and an explicit **Sync now** action.
- WP-Cron schedules (`hourly`, `twicedaily`, and `daily`) plus a 15-minute,
  option-backed concurrency lock.
- Structured log table with a 30-day / 5,000-row retention policy.
- Docker Compose environment with WordPress, MariaDB, WP-CLI, Composer, and a
  deterministic mock API.

## Requirements

- Docker and Docker Compose
- Git

PHP and Composer are provided through Docker, so neither is required on the
host machine.

## Quick start

1. Create the local environment file:

   ```bash
   cp .env.example .env
   ```

2. Start the services:

   ```bash
   docker compose up -d
   ```

3. Install WordPress, configure rewrites, and activate the plugin:

   ```bash
   docker compose run --rm wpcli wp core install --url=http://localhost:8080 --title="Property Sync" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
   docker compose run --rm wpcli wp rewrite structure '/%postname%/' --hard
   docker compose run --rm wpcli wp plugin activate property-sync
   ```

Open WordPress at <http://localhost:8080>, sign in with `admin` / `admin`, and
open **Property Sync** in the admin menu. These credentials are for local
development only.

For the bundled API, configure:

```text
API URL: http://mock-api:3000/properties
API token: demo-token
Sync interval: Disabled (or a desired native WP-Cron interval)
```

The mock API is also exposed to the host at
<http://localhost:3001/properties>. Its full payload contract and failure
scenarios are in [docs/api-contract.md](docs/api-contract.md).

## Synchronization flow

```text
Manual action / WP-Cron
          |
          v
      SyncRunner
       |       |
       |       +-- acquire 15-minute lock
       v
PropertyApiClient -> PropertyNormalizer -> PropertyHasher
                                            |
                                            v
                                     PropertyRepository
                                      create / update / skip
                                            |
                                            v
                                       SyncLogger
```

The external ID is required and is stored as `_property_external_id`. For each
valid listing, relevant canonical fields are recursively key-sorted and hashed
with SHA-256. A matching `_property_sync_hash` skips the post; a changed hash
updates it. The API's `updated_at` remains audit information rather than the
only change signal.

An invalid individual item is logged and does not stop its run. A transport,
HTTP, JSON, or response-envelope error stops the run safely. Items missing from
the source are deliberately not removed in this MVP.

See [docs/architecture.md](docs/architecture.md) for component boundaries,
data storage, and security decisions.

## Configuration and security

Only users with `manage_options` can configure or run a synchronization.
Settings use one structured option, `property_sync_settings`; the token option
is not autoloaded and the UI never renders its saved value. Submitting an empty
token keeps the previous token.

For deployments, define the token outside the database:

```php
define( 'PROPERTY_SYNC_API_TOKEN', 'replace-with-a-secret' );
```

The constant takes precedence and makes the admin field read-only. Production
endpoints must use HTTPS; HTTP is accepted only for `localhost`, `127.0.0.1`,
`::1`, and Docker's local `mock-api` host. Tokens, authorization headers, and
credential-shaped log context are never persisted to activity logs.

## Scheduling and logs

The `property_sync_run_scheduled` event uses WordPress's native `hourly`,
`twicedaily`, or `daily` schedules. Saving a different interval rebuilds the
single scheduled event; choosing **Disabled** removes it. WP-Cron runs when
WordPress receives traffic, so production sites with time-sensitive feeds
should trigger `wp-cron.php` from the system scheduler.

Each run uses `property_sync_lock`, an atomic non-autoloaded option with a
15-minute TTL. Expired locks can be recovered and only their owner token can
release them. The lock is released in `finally`, including API failures.

Events live in the `{$wpdb->prefix}property_sync_logs` table. The dashboard
shows the latest 50, and cleanup retains at most 30 days or 5,000 rows.

## Development commands

```bash
# Environment status and logs
docker compose ps
docker compose logs -f wordpress

# Composer validation, coding standards, and unit tests
docker compose run --rm composer-install validate --strict
docker compose run --rm composer-install check
docker compose run --rm composer-install lint:fix

# Plugin status
docker compose run --rm wpcli wp plugin status property-sync
```

Do not run `docker compose down -v` unless you intentionally want to remove the
local database and uploads. Plain `docker compose down` keeps them.

## Tests

The project uses PHPUnit for pure normalization/hash behavior, PHPCS with
WordPress Coding Standards and PHPCompatibility for static checks, and
WordPress smoke scripts for integration behavior.

```bash
docker compose run --rm composer-install check

docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/content-model.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/admin-settings.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/api-client.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/api-client-mock.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/property-normalization.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/property-sync.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/sync-logging.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/manual-sync-dashboard.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/cron-and-lock.php
```

GitHub Actions runs the static checks and unit tests for pull requests and for
pushes to `develop` and `main`.

## Scope and roadmap

This MVP intentionally excludes automatic deletion of missing listings,
image sideloading, CSV export, custom cron intervals, WP-CLI commands,
incremental cursors, queues, Redis, multisite, GraphQL, webhooks, and
bidirectional synchronization. Those are possible next steps once a concrete
production requirement justifies their complexity.

## Project workflow

Work branches from `develop`, uses Conventional Commits, and is merged back
with review. Releases are promoted from `develop` to `main`. The CI workflow,
issue forms, and pull request template are included in `.github/`.

## License

Copyright (c) 2026 Andrey da Hora Pirola.

This project is licensed under [GPL-2.0-or-later](LICENSE). You may use,
modify, and redistribute it under the GNU General Public License, version 2 or
any later version published by the Free Software Foundation.
