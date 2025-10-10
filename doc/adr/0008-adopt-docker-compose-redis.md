# 8. Adopt Redis via Docker Compose

Date: 2025-10-10

## Status

Accepted

## Context

Phlag relies on a fast, in-memory cache to store feature flag snapshots, evaluation results, and rate-limiting counters. The cache must be low-latency, support pub/sub patterns for cache invalidation, and integrate cleanly with the project’s local-first workflow. Since the application now runs against Docker Compose services, Redis should follow the same pattern to keep the developer experience consistent.

## Decision

Run Redis as a Docker Compose service (`redis`) using the official container image. The service is attached to the shared `phlag` bridge network without exposing port `6379` to the host; the application connects via Docker DNS (`redis`) using the `REDIS_URL` defined in `.env.local`. Optional Compose volumes can be defined if persistence is useful for debugging, but the default configuration keeps the cache ephemeral to mirror production behavior.

## Consequences

Positive

-   Every contributor uses the same Redis version, minimizing behavior differences across machines.
-   Containers can be restarted quickly to clear cache state during development.
-   No external infrastructure or credentials are needed, keeping setup lightweight.

Negative

    -   Requires Docker; contributors must have a local engine available.
    -   Direct host-to-Redis access requires `docker compose exec` or temporary port mappings because the service stays internal.
-   Lacks managed monitoring and failover—additional tooling would be required if resilience becomes a goal.
-   Cached data is lost when the container stops unless persistence volumes are explicitly configured.
