# 11. Invalidate Redis flag caches via pub/sub

Date: 2025-10-11

## Status

Accepted

## Context

Flag evaluation must stay fast while reflecting project changes within seconds. Redis already powers cached flag snapshots and evaluation payloads (ADR 8), but we lacked a documented plan for expiring or refreshing those entries when flags mutate. Multiple API workers and background jobs will run concurrently, so the invalidation mechanism needs to notify every instance without relying on shared in-memory state.

## Decision

-   Model cache entries with deterministic keys: `flag:snapshot:{project}:{environment}` stores the full flag collection for evaluation, and `flag:evaluation:{project}:{environment}:{flag}:{hash}` stores user-specific evaluation results. All structures are JSON-encoded strings with a default TTL of five minutes.
-   Maintain a lightweight index per environment at `flag:evaluation:index:{project}:{environment}` to delete cached evaluations without scanning Redis when mutations occur.
-   When flags, projects, or environments change, the domain layer publishes an event to the Redis channel `phlag.flags.invalidated` containing the affected project and environment identifiers. Listeners delete matching keys (`DEL`) and optionally trigger eager rebuilds.
-   The `cache:warm {project} {env}` CLI command subscribes to the same message bus when running in daemon mode. It precomputes snapshots after deploys and offers operators a manual way to refresh caches without restarting services.
-   HTTP workers subscribe to the invalidation channel during boot. On receipt, they evict in-memory copies and allow the next request to repopulate Redis, ensuring horizontally scaled replicas stay in sync.

## Consequences

Positive

-   A single pub/sub channel keeps cache coherence across the Laravel Zero CLI, future HTTP bridge, and any background workers.
-   Deterministic key names simplify observability: operators can inspect or purge entries with standard Redis tooling.
-   Time-bound TTLs provide an automatic safety net if an invalidation is missed, limiting staleness to minutes.

Negative

-   Workers must maintain a long-lived Redis subscription; reconnect logic and back-off handling add operational complexity.
-   Cache warmers and consumers need to respect payload schemas. Future schema changes require coordinating deploys to avoid deleting the wrong keys.
-   Pub/sub is best-effort; we may still need explicit `cache:warm` runs after large migrations or Redis restarts to recover quickly.
