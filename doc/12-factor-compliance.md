# 12-Factor Compliance Overview

Date: 2025-10-10

This document tracks how the Phlag project aligns with the [12-Factor App](https://12factor.net/) methodology and highlights any outstanding work.

## I. Codebase

-   Single Git repository (`phlag`) for application code and documentation.
-   Trunk-based development with GitHub Actions enforcing CI status checks.

## II. Dependencies

-   PHP dependencies are explicitly declared in `composer.json` and installed via Composer.
-   The `app` container image installs required PHP extensions (`pdo_pgsql`, `zip`, `redis`) so runtime binaries match the code’s expectations.
-   Container images built in CI ensure consistent dependency versions across environments.

## III. Config

-   Runtime configuration stored in `.env.local` and exported so PHP reads everything from environment variables.
-   Docker Compose services load the same file via `env_file`, ensuring containerised processes see identical credentials and service hostnames (e.g., `postgres`, `redis`).
-   No secrets or per-environment configuration committed to the repo. **Action:** audit Laravel config files as features land to ensure they read from `env()` only.

## IV. Backing Services

-   PostgreSQL and Redis run as Docker Compose services on the shared `phlag` bridge network; the application reaches them using Docker DNS (`postgres`, `redis`).
-   External SaaS integrations are not required for the local deployment; if added later they should surface configuration purely through environment variables.

## V. Build, Release, Run

-   GitHub Actions builds artefacts (Composer install, lint, tests) for every push and pull request.
-   Releases are manual: developers pull the latest code, export environment variables, and run the stack via `docker compose up`.
-   Runtime execution is handled by the `app` container’s built-in PHP server communicating with backing services over the internal Docker network.
-   Optional flow for sharing pre-built Docker images is documented in `doc/docker-image-sharing.md`.

## VI. Processes

-   Application runs as stateless Laravel Zero commands; HTTP workers can be launched as needed using the same environment variables.
-   CLI commands (`php phlag …`) operate as one-off tasks; future background jobs should follow the same pattern.

## VII. Port Binding

-   The `app` container binds to port 80 and is the only service published to the host/LAN.
-   PostgreSQL and Redis remain internal-only on the Compose network.
-   Laravel Zero CLI remains headless and is invoked via `php phlag`.

## VIII. Concurrency

-   Horizontal scalability for demos is manual—run additional PHP processes if necessary (see `doc/docker-worker-scaling.md` for Docker Compose scaling patterns).
-   PostgreSQL and Redis containers can be reconfigured with Docker Compose overrides when load testing locally.

## IX. Disposability

-   Containers are stateless; stopping Docker Compose tears services down cleanly while preserving volumes if configured.
-   Docker Compose health checks gate readiness for `app`, `postgres`, and `redis`, improving shutdown/startup signalling and reducing flapping during restarts.
-   CI pipelines ensure rapid spin up of new instances from immutable images.

## X. Dev/Prod Parity

-   Development and "production" are the same local Docker Compose stack; no drift between environments.
-   GitHub Actions exercises the same composer/test workflow used locally.
-   Docker Desktop and Engine minimum versions, plus troubleshooting guidance, are tracked in `doc/docker-troubleshooting.md` so contributors can align their setups quickly.

## XI. Logs

-   Application logs written to stdout; developers inspect them via `docker compose logs` or `docker compose logs app`.
-   **Action:** decide whether to persist logs to disk volumes for longer local analysis.

## XII. Admin Processes

-   One-off tasks executed via Laravel Zero commands (`php phlag app:migrate`, etc.) inside the `app` container.
-   Documented helper scripts (`./scripts/app-cli`, `./scripts/app-migrate`, `./scripts/app-seed`) wrap `docker compose exec app ...`
    for detached scenarios.

---

### Next Actions Summary

1. Ensure new Laravel configuration reads from environment variables; avoid embedding defaults that vary per environment.
2. ✅ Provide guidance for building optional Docker images if teammates want to share artifacts (see `doc/docker-image-sharing.md`).
3. ✅ Capture Docker version/system requirements and troubleshooting steps for local contributors (see `doc/docker-troubleshooting.md`).
4. ✅ Capture worker scaling guidance for Docker Compose (see `doc/docker-worker-scaling.md`).
5. ✅ Document helper scripts for running one-off admin tasks while Docker Compose services are detached.
