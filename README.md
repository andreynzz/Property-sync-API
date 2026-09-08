# Property Sync API

WordPress plugin that imports and synchronizes real-estate listings from an
external REST API. The project demonstrates a production-oriented WordPress
integration with idempotent synchronization, WP-Cron, structured logs, tests,
and a professional Git workflow.

## Current status

The bootstrap, property content model, and admin settings milestones are complete. The repository currently provides:

- an activatable WordPress plugin with PSR-4 autoloading;
- a public property post type with REST-enabled taxonomies and typed metadata;
- an administrator-only settings dashboard for the API URL, masked token, and sync interval;
- a Docker-based WordPress development environment;
- a deterministic mock property API;
- WP-CLI and Composer containers for host-independent tooling.

## Requirements

- Git
- Docker with Docker Compose

PHP and Composer do not need to be installed on the host.

## Quick start

1. Create the local environment file:

   ```bash
   cp .env.example .env
   ```

2. Start the environment:

   ```bash
   docker compose up -d
   ```

3. Install WordPress and activate the plugin:

   ```bash
   docker compose run --rm wpcli wp core install --url=http://localhost:8080 --title="Property Sync" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
   docker compose run --rm wpcli wp rewrite structure '/%postname%/' --hard
   docker compose run --rm wpcli wp plugin activate property-sync
   ```

WordPress will be available at <http://localhost:8080> and the mock API at
<http://localhost:3001/properties>. The local WordPress credentials are
`admin` / `admin` and must never be reused outside development.

The API is also reachable from WordPress containers at:

```text
http://mock-api:3000/properties
```

Use the demo bearer token `demo-token` when testing the endpoint.

## Useful commands

```bash
docker compose ps
docker compose logs -f wordpress
docker compose run --rm composer-install validate --strict
docker compose run --rm wpcli wp plugin status property-sync
docker compose down
```

Use `docker compose down -v` only when you intentionally want to delete the
local WordPress database and uploaded files.

## Smoke tests

Run the content model check against the local WordPress installation:

```bash
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/content-model.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/admin-settings.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/api-client.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/api-client-mock.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/property-normalization.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/property-sync.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/sync-logging.php
docker compose run --rm wpcli wp eval-file wp-content/plugins/property-sync/tests/Smoke/manual-sync-dashboard.php
```

The checks cover the content model plus settings validation, token preservation,
the deployment-level token override, the paginated API client, persistence, and
structured logging.

## Development workflow

Feature and chore branches are created from `develop` and merged back through
pull requests. Stable releases are promoted from `develop` to `main`.

## Planned capabilities

- Property custom post type and taxonomies
- Authenticated REST API client
- Idempotent create, update, and skip synchronization
- Manual and WP-Cron execution
- Concurrency protection and structured logs
- Automated tests, WordPress Coding Standards, and CI
