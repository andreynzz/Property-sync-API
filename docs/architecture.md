# Architecture

## Purpose

Property Sync API imports an external real-estate feed into WordPress without
turning WordPress into a second source of truth. The source API owns listing
data; WordPress owns its presentation and the local synchronized copy.

## Runtime design

```text
Admin POST                 WP-Cron
    |                         |
    +-----------+-------------+
                v
           SyncRunner
       +--------+--------+
       |                 |
   SyncLock          SyncLogger
       |
       v
PropertyApiClient
       |
       v
PropertyNormalizer -> PropertyHasher -> PropertyRepository
```

`Plugin` is the composition root. It wires concrete dependencies explicitly;
there is no container, repository abstraction layer, or queue because the MVP
has one external source and one synchronous workflow.

| Component | Responsibility |
| --- | --- |
| `Admin\Settings` | Validates and reads the structured connection option. |
| `Admin\AdminPage` | Renders settings, last result, manual action, and logs. |
| `Admin\ManualSyncAction` | Authorizes the POST request and applies PRG. |
| `Api\PropertyApiClient` | Uses the WordPress HTTP API for authenticated pagination. |
| `Sync\PropertyNormalizer` | Validates the upstream shape and produces canonical data. |
| `Sync\PropertyHasher` | Builds a stable SHA-256 hash of relevant content. |
| `Sync\PropertyRepository` | Finds, creates, and updates property posts and terms. |
| `Sync\SyncRunner` | Orchestrates a run and isolates per-item failures. |
| `Sync\SyncLock` | Provides option-backed mutual exclusion with a TTL. |
| `Logging\SyncLogger` | Stores safe events, the last result, and retention cleanup. |
| `Cron\SyncScheduler` | Maintains one native WP-Cron event and invokes the same runner. |

## WordPress data model

`property` is public, REST-enabled, supports title/editor/thumbnail, and has a
`/properties/` archive. Its public hierarchical taxonomies are
`property_type`, `property_city`, and `property_status`. `neighborhood` stays
post meta in this MVP.

Internal post meta is deliberately not exposed in REST:

```text
_property_external_id          _property_price
_property_neighborhood         _property_bedrooms
_property_bathrooms            _property_area
_property_image_url            _property_external_updated_at
_property_sync_hash            _property_last_synced_at
```

Logs use a dedicated `{$wpdb->prefix}property_sync_logs` table. It keeps the
run ID, level, event, optional external ID, safe message/context, and UTC
timestamp, with indexes appropriate for recent-run and operational queries.
The last summary and lock use non-autoloaded options.

## Synchronization rules

1. The runner atomically acquires `property_sync_lock` and generates a run ID.
2. The API client fetches at most 100 pages of 50 listings, validating each
   response envelope before yielding it.
3. The normalizer rejects malformed items and removes unknown upstream fields.
4. The repository finds posts by the exact external ID. Multiple matches are
   integrity errors, never an arbitrary selection.
5. A new ID creates a post; an equal content hash skips it; a changed hash
   updates it.
6. Events and counters are recorded, retention runs, and the lock is released
   in `finally`.

An upstream-level failure ends the run because later pages cannot be trusted.
A bad listing is contained to that listing, so other valid records still sync.
Listings absent from later source responses are retained.

## Security decisions

- Settings and manual runs require `manage_options`; the manual action also
  checks its nonce and redirects after POST.
- API URLs require HTTPS except known local development hosts. The HTTP client
  uses safe requests, timeouts, redirect limits, and payload/page limits.
- Saved tokens are masked in the UI, are not autoloaded, and can be overridden
  with `PROPERTY_SYNC_API_TOKEN` supplied by deployment configuration.
- Output is escaped at render time. Incoming settings, payload fields, and
  database inputs are sanitized or validated at their boundary.
- Logs redact credential-shaped context keys and avoid headers, tokens, and raw
  external payloads.
- The lock uses `add_option()` for atomic first acquisition and conditional
  replacement/deletion for stale recovery and ownership-safe release.

## Trade-offs

The plugin uses WordPress posts, taxonomies, post meta, options, the HTTP API,
and WP-Cron rather than custom infrastructure. It is intentionally synchronous:
this keeps behavior transparent and easy to test for a portfolio-sized import.
For higher volume, a future version can introduce batching or Action Scheduler
after operational requirements make that trade-off worthwhile.
