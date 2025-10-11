# 12. Persist audit events in Postgres

Date: 2025-10-11

## Status

Accepted

## Context

Feature flag changes must be auditable for compliance, incident response, and customer transparency. The schema already introduces an `audit_events` table and Eloquent model, but the architectural rationale—and the expectations for retention, indexing, and downstream access—were not recorded. We also need to ensure audit recording does not slip behind business transactions or require additional infrastructure before the service ships.

## Decision

-   Store audit records in the primary Postgres database using the `audit_events` table: UUID primary keys, optional foreign keys to projects/environments/flags, JSONB columns for `changes` and `context`, and a timezone-aware `occurred_at` column. Unique identifiers allow events created during seeding or migrations to be idempotent.
-   Capture audit events synchronously within the same database transaction as the mutation being tracked. Domain services compose payloads describing `action`, `actor_type`, `actor_identifier`, and change deltas so that API responses only succeed when the audit row persists.
-   Expose audit data through two channels: a paginated REST endpoint (`GET /v1/audit-events`) planned alongside CLI tooling (`audit:tail`) for operational staff. Both consumers rely on the indexed columns (`occurred_at`, `project_id`, `flag_id`) defined in the migration to filter efficiently.
-   Establish a retention policy of 365 days by default. A scheduled CLI command will archive or delete older records, allowing future integration with external log pipelines without blocking current deployments.

## Consequences

Positive

-   Co-locating audit logs with transactional data keeps the write path simple and consistent—no additional services are required to ship the MVP.
-   JSONB payloads capture high-fidelity diffs and contextual metadata, enabling precise replay or customer-facing audit trails later.
-   Index coverage supports low-latency queries for touchy flows like “who toggled this flag in the last hour?”.

Negative

-   Synchronous writes increase the size and duration of primary transactions; bulk updates may need batching to avoid contention.
-   Postgres storage grows quickly if retention jobs fail; operators must monitor table size and vacuum activity.
-   Migrating to a streaming audit service later will require building a replication pipeline from the canonical Postgres table.
