# Repository Guidelines

## Project Structure & Module Organization
- `app/` holds Laravel Zero commands, services, and domain logic; group new features by bounded context under `App\` namespaces.
- `config/` stores environment-dependent settings; mirror any new config keys in `doc/adr/` decisions when relevant.
- `tests/` contains Pest suites split into `Unit` and `Feature`; co-locate test fixtures under `tests/Support`.
- `doc/` captures ADRs and reference material; update records whenever architecture or workflows shift.
- Docker Compose definitions live alongside the app; keep service names and ports stable to avoid breaking docs or scripts.

## Build, Test, and Development Commands
- `composer install` — install PHP dependencies with locked versions.
- `docker compose up -d --build` — start the application, PostgreSQL, and Redis services.
- `docker compose exec app php phlag app:migrate` — run database migrations through the Laravel Zero binary.
- `docker compose exec app php phlag cache:warm {project} {env}` — refresh Redis caches when flag logic changes.
- `composer test` — execute the Pest test suites with PHPUnit under the hood.
- `composer lint` — run Pint (PSR-12) formatting checks; fix violations before committing.
- `composer stan` — perform PHPStan static analysis at the configured level.
- `docker compose logs` — inspect database/cache container output when debugging local issues.

## Coding Style & Naming Conventions
- Follow PSR-12 with 4-space indentation for PHP; prefer explicit return types and constructor property promotion.
- Name classes in `PascalCase`, methods and functions in `camelCase`, and configuration files with snake_case identifiers.
- Keep DTOs immutable, favor value objects for complex flag rules, and avoid facades in domain services.
- For Docker Compose overrides, favor lowercase service names and keep ports consistent with documentation.

## Testing Guidelines
- Write Pest tests using descriptive `it()` blocks; mirror production namespaces under `tests/`.
- Add regression tests alongside bug fixes; aim for coverage on flag evaluation branches and caching paths.
- Run `composer test` locally before pushing; skip relying on CI to reveal deterministic failures.

## Commit & Pull Request Guidelines
- Compose commits in imperative present tense (e.g., `Add flag evaluator cache busting`).
- Group related changes; avoid large mixed commits spanning PHP, infra, and docs unless atomically required.
- Pull requests must include a summary, linked issue or ADR reference, validation evidence (test output or screenshots), and note any infra or secret updates.
- Request review from domain owners (application vs. infrastructure) when touching their areas.

## Security & Configuration Tips
- Store secrets in `.env.local`; never commit `.env*` files or plaintext credentials.
- Use `set -a; source .env.local; set +a` to load local development variables before running commands.
- Keep Postgres and Redis internal to the Docker network; expose only the application’s port 80 when troubleshooting.
- Audit new dependencies for licenses and known CVEs; document security-impacting changes in ADRs.
