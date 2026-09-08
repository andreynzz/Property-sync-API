# Changelog

All notable changes to this project are documented here.

## [1.0.1] - 2026-09-08

### Fixed

- Composer dependency resolution now targets PHP 8.1, keeping the locked
  PHPUnit dependency chain compatible with the CI runtime.
- PHPUnit result caching is disabled to avoid write warnings in mounted local
  development volumes.

### Changed

- Added the GPL-2.0-or-later copyright notice for Andrey da Hora Pirola.

## [1.0.0] - 2026-09-08

### Added

- WordPress plugin bootstrap with Composer PSR-4 autoloading and lifecycle
  hooks.
- Docker development environment with WordPress, MariaDB, WP-CLI, Composer,
  and an authenticated mock property API.
- Public `property` post type, three REST-enabled taxonomies, and typed
  internal metadata.
- Secure settings dashboard with masked API token and deployment-level token
  override.
- Paginated API client, canonical property normalization, deterministic content
  hashing, and idempotent create/update/skip synchronization.
- Structured synchronization log table, retention, last-run summary, manual
  synchronization workflow, WP-Cron scheduling, and a concurrency lock.
- PHPUnit tests, WordPress smoke checks, WordPress Coding Standards,
  PHPCompatibility, GitHub Actions CI, and GitHub issue/PR templates.
- Portfolio documentation for setup, operation, architecture, security,
  synchronization, testing, and MVP limits.

### Security

- Capability and nonce enforcement for administrative actions.
- HTTPS enforcement outside local development hosts, request limits, masked
  tokens, secret-safe logs, and non-autoloaded sensitive options.
