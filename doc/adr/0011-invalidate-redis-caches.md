# 11. Invalidate Redis flag caches via pub/sub

Date: 2025-10-11

## Status

Accepted

## Context

Flag evaluation must stay fast while reflecting project changes within seconds. Redis already powers cached flag snapshots and evaluation payloads (ADR 8), but we lacked a documented plan for expiring or refreshing those entries when flags mutate. Multiple API workers and background jobs will run concurrently, so the invalidation mechanism needs to notify every instance without relying on shared in-memory state.

## Decision

-   Model cache entries with deterministic keys and shared TTLs. Snapshots live at `flag:snapshot:{project}:{environment}` and user-specific evaluations at `flag:evaluation:{project}:{environment}:{flag}:{signature}:{hash}`. All structures are JSON-encoded strings with a default TTL of five minutes (300 seconds).
-   Maintain a lightweight index per environment at `flag:evaluation:index:{project}:{environment}` to delete cached evaluations without scanning Redis when mutations occur. The index expires alongside the evaluation entries.
-   When flags, projects, or environments change, the domain layer publishes an event to the Redis channel `phlag.flags.invalidated` containing the affected project and environment identifiers. Listeners delete matching keys (`DEL`) and optionally trigger eager rebuilds.
-   The `cache:warm {project} {env}` CLI command subscribes to the same message bus when running in daemon mode. It precomputes snapshots after deploys and offers operators a manual way to refresh caches without restarting services.
-   HTTP workers subscribe to the invalidation channel during boot. On receipt, they evict in-memory copies and allow the next request to repopulate Redis, ensuring horizontally scaled replicas stay in sync.

### Key schema and payloads

-   Segment encoding: all `{project}`, `{environment}`, `{flag}`, and `{signature}` segments are lower-cased and sanitised by replacing colons, braces, and whitespace with underscores. This keeps keys human-readable while avoiding Redis hash-slot conflicts.
-   Snapshot entries: `flag:snapshot:{project}:{environment}` stores the JSON produced by `FlagSnapshotFactory::make()`. It includes project metadata, environment metadata, an array of flag definitions, and `generated_at`. TTL: 300 seconds. Snapshot warmers should refresh hot environments on deploy; otherwise cold keys expire naturally.
-   Evaluation entries: `flag:evaluation:{project}:{environment}:{flag}:{signature}:{hash}` stores JSON with:
    -   `variant` (`string|null`)
    -   `reason` (`string`)
    -   `rollout` (`int`)
    -   optional `payload` (`array<string, mixed>`)
    -   optional `bucket` (`int`)

    `{signature}` is a SHA-1 of the flag definition (enabled state, variants, rules, updated_at). `{hash}` is a SHA-1 of the evaluation context (user identifier plus sorted attributes). TTL: 300 seconds. Re-evaluations reuse cached payloads until the flag definition changes or the TTL elapses.
-   Evaluation index: `flag:evaluation:index:{project}:{environment}` is a Redis set of evaluation keys generated for the environment. The index expires after 300 seconds; any membership left behind by TTL drift is cleared when the index key expires.
-   Invalidation payload: published messages are JSON objects with `project` and `environment` keys. Consumers use these identifiers to drop the snapshot, delete the evaluation index, and purge any lingering evaluation keys.

### TTL strategy

-   Five-minute TTLs balance freshness with reduced load on Postgres. Operators can shorten or extend TTLs via the `FLAG_CACHE_SNAPSHOT_TTL` and `FLAG_CACHE_EVALUATION_TTL` environment variables if high churn or read-heavy workloads demand it.
-   Hot environments should be warmed immediately after deploys with `cache:warm` to avoid cold-start latency. Cold environments naturally expire and are generated lazily on demand.
-   Evaluation caches couple TTL with pub/sub invalidations so missed events have a bounded blast radius. Audit metrics should monitor key age and hit rates to refine TTL values over time.

## Consequences

Positive

-   A single pub/sub channel keeps cache coherence across the Laravel Zero CLI, future HTTP bridge, and any background workers.
-   Deterministic key names simplify observability: operators can inspect or purge entries with standard Redis tooling.
-   Time-bound TTLs provide an automatic safety net if an invalidation is missed, limiting staleness to minutes.

Negative

-   Workers must maintain a long-lived Redis subscription; reconnect logic and back-off handling add operational complexity.
-   Cache warmers and consumers need to respect payload schemas. Future schema changes require coordinating deploys to avoid deleting the wrong keys.
-   Pub/sub is best-effort; we may still need explicit `cache:warm` runs after large migrations or Redis restarts to recover quickly.
