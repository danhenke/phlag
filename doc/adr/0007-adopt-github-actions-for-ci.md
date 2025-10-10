# 7. Adopt GitHub Actions for Continuous Integration

Date: 2025-10-10

## Status

Accepted

## Context

Phlag is hosted in GitHub and relies on automated checks (linting, static analysis, tests) to keep the main branch releasable under a trunk-based workflow. The CI solution must integrate tightly with pull requests, support Docker-based jobs that mirror the local Compose stack, and provide simple ways to share build artifacts (e.g., packaged Composer dependencies or Docker images) without introducing heavy infrastructure.

## Decision

Use GitHub Actions as the continuous integration platform. Workflows defined under `.github/workflows/` will run on every push and pull request, executing lint, static analysis, and test suites. Optional workflows can build Docker images that mirror the local Compose setup and upload them as artifacts for teammates to pull down.

## Consequences

Positive

-   Native integration with GitHub repositories provides fast feedback on pull requests and branch protections.
-   Matrix builds can target multiple PHP versions or operating systems to validate portability.
-   Marketplace actions and reusable workflows accelerate pipeline setup for PHP tooling and Docker builds.

Negative

-   Runners are ephemeral; caching dependencies requires deliberate configuration to keep build times low.
-   Usage over free-tier minutes incurs cost based on runner type (especially for self-hosted or larger instances).
-   Vendor lock-in to GitHub’s workflow syntax; migrating to another CI would require translating pipelines.
-   Maintaining Docker build caches requires deliberate configuration to keep pipeline times low.
