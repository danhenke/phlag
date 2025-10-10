# Open Tasks

## Core API & Domain (README.md:17-159)
- [ ] Implement JWT issuance pipeline (`POST /v1/auth/token`) including API key storage, signing keys, and token expiration handling.
- [ ] Build project + environment CRUD endpoints with persistence, validation, and pagination (`GET/POST /v1/projects`, etc.).
- [ ] Implement feature flag CRUD with JSON rule support and schema validation (`/v1/projects/{project}/flags`).
- [ ] Deliver flag evaluation endpoint (`GET /v1/evaluate`) supporting percentage rollouts and user targeting logic.
- [ ] Record audit events for flag and project mutations with retrieval API.
- [ ] Integrate Redis caching for flag snapshots/evaluations, including cache invalidation strategies.
- [ ] Design and run database migrations defining projects, environments, flags, evaluations, audit tables.
- [ ] Seed demo data aligned with README instructions (`docker compose exec app php phlag app:seed`).
- [ ] Replace placeholder HTTP entry point with Laravel Zero/HTTP kernel bridging the documented REST API.
- [ ] Implement consistent error handling and response envelopes across endpoints.

## CLI & Tooling (README.md:160-210)
- [ ] Implement CLI commands: `projects:key:rotate`, `cache:warm`, `audit:tail`, plus ensure `app:migrate`/`app:seed` invoke real logic.
- [ ] Provide helper scripts/aliases wrapping `docker compose exec app ...` for common admin flows.
- [ ] Generate OpenAPI spec via swagger-php and expose `/v1/docs/openapi.json` + Swagger UI route.
- [ ] Produce Postman collection at `/postman/FeatureFlagService.postman_collection.json`.

## Testing & Quality (README.md:192-210)
- [ ] Author Pest test suite covering API endpoints, evaluation paths, caching, and CLI commands.
- [ ] Configure PHPStan/Pint pipelines and ensure codebase passes lint + static analysis.
- [ ] Add GitHub Actions workflows for linting, static analysis, tests, and optional Docker image build.

## Infrastructure & Deployment (README.md:20-125, 217-238)
- [ ] Flesh out Laravel configuration to source all settings from environment variables (12-factor alignment).
- [ ] Provide Docker image build/publish workflow for sharing artifacts (README.md:33).
- [ ] Document scaling guidance for multiple worker processes in Docker Compose.
- [ ] Define Docker version prerequisites and troubleshooting section for contributors.
- [ ] Decide on log retention approach (volumes vs. stdout) and document instructions.
- [ ] Ensure compose stack bundles required PHP extensions (pgsql, redis) and health checks.

## Docs & Experiential Parity
- [ ] Update README with concrete run examples once endpoints/CLI exist (status codes, sample payloads).
- [ ] Publish ADRs or notes for JWT key management, caching invalidation, and audit logging once implemented.
- [ ] Supply ER diagram or schema overview to support onboarding (ties to migrations).

## Roadmap Items (README.md:218-246)
- [ ] Implement role-based access control for projects/environments.
- [ ] Provide official SDKs (PHP + JS) matching the API.
- [ ] Develop UI dashboard for managing flags.
- [ ] Add per-project rate limiting.
- [ ] Deliver user segmentation rule builder/extensions.

## Existing 12-Factor Follow-ups (doc/12-factor-compliance.md:21-75)
- [ ] Audit Laravel config files to ensure all runtime values are pulled via `env()`.
- [ ] Document optional workflow for building and sharing Docker images for teammates.
- [ ] Capture guidance on running multiple PHP worker processes against the Docker Compose network.
- [ ] Record minimum Docker version requirements and troubleshooting steps for contributors.
- [ ] Decide whether to persist container logs via volumes and document the approach.
- [ ] Provide helper scripts or aliases wrapping `docker compose exec app …` for detached admin tasks.
