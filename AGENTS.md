# Repository Guidelines

## Project Structure & Module Organization

-   `app/` holds Laravel Zero commands, services, and domain logic; group new features by bounded context under `App\` namespaces.
-   `config/` stores environment-dependent settings; mirror any new config keys in `doc/adr/` decisions when relevant.
-   `tests/` contains Pest suites split into `Unit` and `Feature`; co-locate test fixtures under `tests/Support`.
-   `doc/` captures ADRs and reference material; update records whenever architecture or workflows shift, and consult `doc/12-factor-compliance.md` for the live 12-Factor checklist.
-   Docker Compose definitions live alongside the app; keep service names and ports stable to avoid breaking docs or scripts.

## Build, Test, and Development Commands

-   `composer install` — install PHP dependencies with locked versions.
-   `docker compose up -d` — start the application, PostgreSQL, and Redis services (Builds the app image locally unless you set `PHLAG_APP_IMAGE` to a prebuilt tag).
-   `docker compose exec app php phlag app:migrate` — run database migrations through the Laravel Zero binary.
-   `docker compose exec app php phlag cache:warm {project} {env}` — refresh Redis caches when flag logic changes.
-   `composer test` — execute the Pest test suites with PHPUnit under the hood.
-   `composer lint` — run Pint (PSR-12) formatting checks; fix violations before committing.
-   `composer stan` — perform PHPStan static analysis at the configured level.
-   `docker compose logs` — inspect database/cache container output when debugging local issues.

## Coding Style & Naming Conventions

-   Follow PSR-12 with 4-space indentation for PHP; prefer explicit return types and constructor property promotion.
-   Name classes in `PascalCase`, methods and functions in `camelCase`, and configuration files with snake_case identifiers.
-   Keep DTOs immutable, favor value objects for complex flag rules, and avoid facades in domain services.
-   For Docker Compose overrides, favor lowercase service names and keep ports consistent with documentation.

## Testing Guidelines

-   Write Pest tests using descriptive `it()` blocks; mirror production namespaces under `tests/`.
-   Add regression tests alongside bug fixes; aim for coverage on flag evaluation branches and caching paths.
-   Run `composer test` locally before pushing; skip relying on CI to reveal deterministic failures.

## Commit & Pull Request Guidelines

-   Compose commits in imperative present tense (e.g., `Add flag evaluator cache busting`).
-   Group related changes; avoid large mixed commits spanning PHP, infra, and docs unless atomically required.
-   Pull requests must include a summary, linked issue or ADR reference, validation evidence (test output or screenshots), and note any infra or secret updates.
-   Request review from domain owners (application vs. infrastructure) when touching their areas.

## Security & Configuration Tips

-   Store secrets in `.env.local`; never commit `.env*` files or plaintext credentials.
-   Use `set -a; source .env.local; set +a` to load local development variables before running commands.
-   Keep Postgres and Redis internal to the Docker network; expose only the application’s port 80 when troubleshooting.
-   Audit new dependencies for licenses and known CVEs; document security-impacting changes in ADRs.

## Issue Handling Checklist

-   Clarify scope: read linked issues and ADRs, restate objectives, and surface dependencies or blockers before coding.
-   Draft a plan when scope is large or ambiguous; outline major steps and confirm with the requester if needed.
-   Respect conventions by following repo guidelines (PSR-12, env-driven config, ADR updates) and note relevant references in changes.
-   When you need upstream framework or library documentation, prefer fetching it via the Context7 MCP tools before searching elsewhere.
-   Work on a feature branch named after the issue; avoid committing directly to the default branch.
-   Test and validate: add or update automated tests for new behavior, run `composer test` and other touched tooling, summarize results, and call out gaps if something cannot be executed.
-   Document changes: update README/ADRs/tests whenever workflows or behavior shift, and note follow-up issues when work is deferred.
-   Communicate status clearly by listing touched files, assumptions, risks, and next actions prior to handoff or review.
-   Open a pull request for review once changes are complete, and request feedback from the appropriate domain owner.
-   Mirror the work summary, validation, notes, and next steps from handoff back into PR comments (or the issue when no PR exists) so reviewers see the full context.
-   Follow GitHub’s PR best practices (https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/getting-started/helping-others-review-your-changes): clear titles/descriptions, linked issues, testing evidence, screenshots/logs as relevant.
-   Maintain traceability by referencing the issue number in branch names, commits, and PR descriptions.
-   Check for dependency or merge conflicts with other active work before landing changes, and coordinate as needed.
-   Confirm release readiness: validate migrations are idempotent, toggles are set, and rollback paths exist.
-   Surface operational or security impacts (new env vars, secrets, services) in the PR notes.
-   Share knowledge after merge—update docs or post a brief summary to keep the team aligned.
-   Post-merge, monitor CI/CD or runtime logs if possible to verify the change behaves as expected.
