# 🚦 Phlag - Feature Flag Service

A lightweight, developer-focused **Feature Flag & Remote Configuration API** built with **Laravel Zero**, **PostgreSQL**, and **Redis**. The project runs entirely on a local **Docker Compose** stack so you can experiment with feature flag workflows without provisioning any cloud infrastructure.

Please refer to [`doc/adr`](./doc/adr) for [Architecture Decision Records](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions) (ADRs) and [`doc/12-factor-compliance.md`](./doc/12-factor-compliance.md) for the 12-Factor alignment checklist.

---

## 🧭 Overview

This service allows developers to **create**, **manage**, and **evaluate feature flags** per project and environment. It demonstrates practical fluency with the PHP ecosystem, including:

-   Laravel-style architecture using Laravel Zero
-   Validation, caching, and JWT authentication
-   PostgreSQL migrations and Redis caching managed through Docker Compose
-   OpenAPI documentation and Postman collection
-   GitHub Actions CI for linting, static analysis, and tests
-   Docker Compose runtime for local development and demos

---

## 🧱 Features

| Category                    | Description                                                    |
| --------------------------- | -------------------------------------------------------------- |
| **Authentication**          | JWT-based bearer tokens per project/environment                |
| **Projects / Environments** | CRUD endpoints for logical grouping and context separation     |
| **Feature Flags**           | CRUD for flags with JSON rule sets                             |
| **Evaluation**              | Percentage rollouts & user targeting                           |
| **Audit Logs**              | Track flag changes and actor actions                           |
| **Redis Caching**           | Cache flag states and evaluations                              |
| **Documentation**           | OpenAPI 3.1 + Swagger UI                                       |
| **CLI**                     | Laravel Zero commands for migrations, cache warm, key rotation |
| **Testing**                 | Pest + PHPUnit for fast, expressive tests                      |

---

## 🧩 Tech Stack

| Layer         | Tool                                       |
| ------------- | ------------------------------------------ |
| Framework     | Laravel Zero                               |
| Language      | PHP 8.4                                    |
| Database      | PostgreSQL (Docker Compose service)        |
| Cache         | Redis (Docker Compose service)             |
| Secrets       | `.env`-style environment variables         |
| Docs          | OpenAPI 3.1 (via swagger-php)              |
| Tests         | PestPHP                                    |
| Hosting       | Docker Compose (app + Postgres + Redis)    |
| CI/CD         | GitHub Actions (lint, static analysis, CI) |
| Observability | Application + Docker logs via stdout       |
| Logging       | Monolog                                    |
| IaC           | Not required for local-only deployments    |

---

## 📁 Project Structure

```
phlag/
├─ app/                 # Commands and application services
├─ bootstrap/           # Laravel Zero bootstrap
├─ config/              # Application configuration
├─ doc/adr/             # Architecture decision records
├─ public/              # HTTP entrypoint served by Docker
├─ tests/               # Unit and feature tests
├─ vendor/              # Composer dependencies
├─ phlag                # Laravel Zero console binary
├─ composer.json
└─ README.md
```

---

## ⚙️ Installation

### Prerequisites

- Docker Desktop 4.32.0+ (Engine 26.1.1, Compose 2.27+) on macOS or Windows with virtualization enabled (HyperKit on Apple Silicon, WSL 2 on Windows).
- Docker Engine 26.0.0+ with the Compose plugin 2.27+ on Linux; install the `docker-compose-plugin` package provided by your distribution.
- BuildKit enabled for modern Dockerfile syntax. Add the following to `~/.docker/config.json` (see Docker documentation) or export `DOCKER_BUILDKIT=1` before building:

    ```json
    {
        "features": {
            "buildkit": true
        }
    }
    ```

Need more detail? The troubleshooting guide in [`doc/docker-troubleshooting.md`](./doc/docker-troubleshooting.md) covers version checks, disk space tips, and recovery steps.

### 1. Clone & install dependencies

```bash
git clone https://github.com/danhenke/phlag.git
cd phlag
composer install
```

### 2. Create a local environment file

Define the runtime configuration expected by the application:

```bash
cat <<EOF > .env.local
APP_NAME=Phlag
APP_ENV=local
APP_DEBUG=true
POSTGRES_DB=phlag
POSTGRES_USER=postgres
POSTGRES_PASSWORD=postgres
DB_URL=pgsql:host=postgres;port=5432;dbname=${POSTGRES_DB}
REDIS_URL=redis://redis:6379/0
JWT_SECRET=$(openssl rand -base64 32)
EOF
```

Load the variables into your shell whenever you start a new terminal:

```bash
set -a
source .env.local
set +a
```

### 3. Start Docker Compose stack

Ensure Docker Desktop (or another Docker engine) is running, then bring up the full stack defined in `compose.yaml`:

```bash
docker compose up -d --build
```

This launches three services within an isolated Docker network:

-   `app` — PHP runtime serving HTTP on port 80 and talking to backing services via Docker DNS.
-   `postgres` — PostgreSQL database available only on the Compose network.
-   `redis` — Redis cache available only on the Compose network.

Only the application’s port 80 is published to your LAN (`http://localhost/` by default).

Logs remain on stdout; tail them with `docker compose logs -f` when debugging. The Compose file configures the `json-file` driver with `max-size=10m` and `max-file=5`, so each container keeps a rotating local buffer without requiring extra volumes.

### 4. Run migrations and seed data

Execute CLI commands inside the `app` container so they share networking and environment context. Helper scripts wrap
`docker compose exec` for the common admin tasks when your stack is running detached:

```bash
./scripts/app-migrate --seed
# or when you just need to reload demo data
./scripts/app-seed --fresh
```

The seeders provision a reusable dataset:

-   Project `demo-project` with Production (default) and Staging environments.
-   Feature flags `checkout-redesign` and `homepage-recommendations`.
-   Sample evaluation and audit records that exercise the schema.

### 5. Verify the HTTP endpoint and CLI

-   Visit `http://localhost/` to confirm the container is serving traffic.
-   List available console commands from inside the container:

    ```bash
    docker compose exec app php phlag list
    ```

### Optional: Share a pre-built image

If a teammate needs a ready-to-run container, follow the workflows in `doc/docker-image-sharing.md`.
They cover exporting the locally built `phlag-app` image as a `.tar` archive or publishing it to a registry
such as GHCR with traceable tags.

---

## 🌐 API Overview

| Endpoint                                    | Description                                     |
| ------------------------------------------- | ----------------------------------------------- |
| `POST /v1/auth/token`                       | Exchange API key for JWT                        |
| `GET /v1/projects`                          | List projects                                   |
| `POST /v1/projects`                         | Create a project                                |
| `GET /v1/projects/{project}/flags`          | List flags                                      |
| `POST /v1/projects/{project}/flags`         | Create flag                                     |
| `PATCH /v1/projects/{project}/flags/{key}`  | Update flag                                     |
| `DELETE /v1/projects/{project}/flags/{key}` | Delete flag                                     |
| `GET /v1/evaluate`                          | Evaluate flag (`?project=&env=&flag=&user_id=`) |
| `GET /v1/docs/openapi.json`                 | OpenAPI JSON spec                               |

> Full examples are available in `/postman/FeatureFlagService.postman_collection.json`.

### Example: Exchange an API key for a JWT

```bash
curl --request POST \
     --url http://localhost/v1/auth/token \
     --header 'Content-Type: application/json' \
     --data '{
         "project": "demo-project",
         "environment": "production",
         "api_key": "<project-api-key>"
     }'
```

```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_in": 3600,
    "project": "demo-project",
    "environment": "production"
}
```

### Example: List seeded projects and environments

After running the demo seeders you can inspect the REST payloads with a simple `curl`:

```bash
curl --request GET \
     --url 'http://localhost/v1/projects' \
     --header 'Authorization: Bearer <jwt>'
```

```json
{
    "data": [
        {
            "id": "11111111-1111-4111-9111-111111111111",
            "key": "demo-project",
            "name": "Phlag Demo Project",
            "description": "Sample project used to showcase feature flag workflows.",
            "metadata": {
                "owner": "demo@phlag.test",
                "timezone": "UTC"
            },
            "environments": [
                {
                    "id": "22222222-2222-4222-9222-222222222222",
                    "key": "production",
                    "name": "Production",
                    "is_default": true
                },
                {
                    "id": "33333333-3333-4333-9333-333333333333",
                    "key": "staging",
                    "name": "Staging",
                    "is_default": false
                }
            ]
        }
    ]
}
```

### Example: Evaluate a feature flag for a user

```bash
curl --request GET \
     --url 'http://localhost/v1/evaluate?project=demo-project&env=production&flag=checkout-redesign&user_id=user-123&country=US&segment=beta-testers' \
     --header 'Authorization: Bearer <jwt>'
```

```json
{
    "flag": "checkout-redesign",
    "variant": "variant",
    "rollout": 75,
    "reason": "matched_segment_rollout",
    "request_context": {
        "country": "US",
        "segment": "beta-testers"
    }
}
```

---

## 🔐 Authentication

-   JWT bearer tokens issued via `/v1/auth/token` using project API key.
-   Include header:
    ```
    Authorization: Bearer <jwt>
    ```
-   Tokens are scoped to project + environment.

---

## 🧰 CLI Commands (Laravel Zero)

| Command                          | Purpose                 |
| -------------------------------- | ----------------------- |
| `app:migrate [--fresh] [--seed]` | Run database migrations |
| `app:seed [--fresh]`             | Seed demo data          |
| `app:hello`                      | Print a hello message   |

Run commands through the Laravel Zero binary inside the running container via the helper script:

```bash
./scripts/app-cli app:migrate
```

### Example: Run migrations and seed the demo dataset

Use the helper to execute Laravel Zero commands in the app container and mirror what CI does before tests run:

```bash
./scripts/app-migrate --seed
```

This wraps `docker compose exec app php phlag app:migrate --seed` and surfaces the same confirmation messages you would see by running the binary directly:

```
Database migrations completed.
Database seeding completed.
```

### Example: Refresh demo data from a clean schema

```bash
./scripts/app-seed --fresh
```

The command drops and recreates the schema before seeding so you always have the canonical records from `database/seeders/DatabaseSeeder.php`:

```
Database seeding completed.
```

### Example: Verify the CLI wiring inside the container

Use the sample `app:hello` command to confirm you can execute Laravel Zero commands through the helper:

```bash
./scripts/app-cli app:hello
```

```text
Hello from Phlag!
```

---

## 🧪 Testing

```bash
composer test
```

Uses [**PestPHP**](https://pestphp.com/) for expressive tests and [PHPStan](https://phpstan.org/) for static analysis.

---

## 🔁 Development Workflow (GitHub Flow)

The project follows [GitHub Flow](https://docs.github.com/en/get-started/using-github/github-flow) to coordinate work with Codex Cloud tasks:

1.  Start new work from an up-to-date `main` branch.
2.  Create a feature branch named `issue/<number>-<slug>` (for example, `issue/16-adopt-github-flow`).
3.  Implement the change with small, imperative commits that reference the issue in the commit body or pull request description.
4.  Run validation locally (`composer test`, `composer lint`, `composer stan`, etc.) and capture the output or call out blockers when tooling cannot run (e.g., Composer downloads requiring GitHub tokens).
5.  Open a pull request summarizing the work, linking the issue, and attaching validation evidence (test logs, screenshots, or notes) using the template in `.github/pull_request_template.md`.
6.  After approval, merge via fast-forward or squash, then delete the branch. Follow-up tasks start their own issue and branch.

PR descriptions should also mention any migrations, environment variables, or operational impacts so reviewers can plan rollouts.

---

## 📘 API Documentation

-   Auto-generated from annotations (`swagger-php`)
-   OpenAPI JSON: `/v1/docs/openapi.json`
-   Swagger UI: `/docs`
-   Postman Collection: `/postman/FeatureFlagService.postman_collection.json`

Regenerate docs once you have sourced environment variables:

```bash
php api/swagger.php > docs/openapi.json
```

---

## 🛠️ Local Deployment

The service is intended for local demonstrations:

1. `docker compose up -d --build` to launch the app, PostgreSQL, and Redis on the shared network.
2. Export environment variables from `.env.local` (optional when running host-side tooling).
3. Use `docker compose exec app ...` for Laravel Zero commands so they share the same networking configuration as the HTTP service.

When you are finished experimenting, shut everything down with `docker compose down`.

Need to scale HTTP workers or spawn dedicated CLI worker containers? Follow the patterns documented in `doc/docker-worker-scaling.md`.

### Container runtime details

-   The `app` image pre-installs the PHP extensions required by Laravel + Redis (`pdo_pgsql`, `zip`, and `redis`) and provides `curl` for internal health probes.
-   Docker Compose health checks monitor the `app`, `postgres`, and `redis` services; the application container now waits for the data stores to become healthy before starting its HTTP server.

---

## 🧭 Development Workflow

| Step            | Command                                |
| --------------- | -------------------------------------- |
| Lint            | `composer lint`                        |
| Static analysis | `composer stan`                        |
| Tests           | `composer test`                        |
| CI              | GitHub Actions run lint + test on push |
| Deploy          | `docker compose up -d` (local only)    |

---

## 🧩 Roadmap

-   [ ] Role-based access control (RBAC)
-   [ ] SDKs (PHP + JS)
-   [ ] UI dashboard for managing flags
-   [ ] Rate limiting per project
-   [ ] User segmentation rules

---

## 📜 License

MIT © 2025 Dan Henke

---

### 🧠 Summary

This project showcases a **real-world PHP architecture** suitable for production-style APIs while remaining lightweight. With the move to Docker Compose, you can spin it up locally in minutes to explore clean design, testing, validation, and deployment discipline without relying on external cloud accounts.
