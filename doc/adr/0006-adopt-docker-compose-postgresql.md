# 6. Adopt PostgreSQL via Docker Compose

Date: 2025-10-10

## Status

Accepted

## Context

Phlag requires a relational database that provides transactional guarantees, JSONB support, and mature tooling. The project is now developed and demonstrated entirely on a local workstation, so the database must be easy to spin up, reset, and share with collaborators without relying on managed cloud services. Docker Compose is already used to provision other dependencies, making it a natural fit for hosting PostgreSQL in a repeatable way.

## Decision

Run PostgreSQL as a Docker Compose service (`postgres`) using the official container image. Configuration (database name, user, password) is supplied via environment variables sourced from `.env.local`, and data is persisted to a local volume so it survives container restarts. The service is attached to the shared `phlag` bridge network without publishing port `5432` to the host; the application reaches it via Docker DNS (`postgres`) using the configured DSN.

## Consequences

Positive

-   All contributors share an identical PostgreSQL version and configuration, eliminating “works on my machine” drift.
-   Containers can be torn down and recreated in seconds, enabling clean-room testing of migrations and seed data.
-   No cloud credentials or infrastructure provisioning are required, reducing setup time.

Negative

    -   Requires Docker to be installed and running; contributors without container support cannot follow the workflow.
    -   Direct connections from host tools require `docker compose exec` or temporary port publishing because the service is not exposed outside the Compose network.
-   Local disk persistence must be managed (e.g., cleaning volumes) to avoid stale data when schemas change significantly.
-   High-availability and automated backups are not provided; separate tooling is needed if those become requirements.
