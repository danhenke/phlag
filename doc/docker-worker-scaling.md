# Running Multiple PHP Workers with Docker Compose

Date: 2025-10-11

This guide explains how to run additional PHP worker processes alongside the default `app` service defined in `compose.yaml`. Use it when you want to exercise the 12-Factor “Concurrency” principle locally—whether that means scaling HTTP handlers for heavy traffic demos or running long-lived CLI workers that share the same PostgreSQL and Redis backing services.

## Prerequisites

- The baseline stack (`docker compose up -d`) is running and healthy.
- `.env.local` contains the credentials referenced by `compose.yaml`, and you have exported them in your shell (`set -a; source .env.local; set +a`) before launching helper scripts.

Verify that all services are running before scaling:

```bash
docker compose ps --status=running
docker compose top
```

Both commands should list the `app`, `postgres`, and `redis` processes (see the Docker Compose command reference for details on `ps` and `top` output formatting).

## Scaling Additional HTTP Workers

The `app` service uses PHP’s built-in server bound to port 80. You can ask Docker Compose to launch extra containers for this service:

```bash
docker compose up -d --scale app=3
```

Key notes:

- Only one container can publish port `80` on the host. Docker Compose keeps the first replica exposed and attaches the remaining replicas to the internal `phlag` network. For local load-testing, target the service name (`http://app:80`) from another container (for example, `docker compose run --rm app curl http://app:80/health`).
- Keep an eye on resource usage with `docker stats app` and adjust Docker Desktop’s CPU/RAM allocation if requests begin to queue.
- When you are done, scale back down with `docker compose up -d --scale app=1` or stop the replicas via `docker compose stop app`.

## Running Dedicated CLI Worker Containers

For long-lived CLI workers (queue consumers, cache warmers, schedulers), define a lightweight override file—`compose.workers.yaml`—next to the primary `compose.yaml`:

```yaml
# compose.workers.yaml
services:
  worker:
    image: ${PHLAG_APP_IMAGE:-ghcr.io/danhenke/phlag:latest}
    command: php phlag <worker-command>
    env_file:
      - .env.local
    environment:
      APP_ENV: ${APP_ENV:-local}
    depends_on:
      - postgres
      - redis
    networks:
      - phlag
    profiles:
      - workers
```

Replace `<worker-command>` with the long-running Laravel Zero command you want to execute (for example, a future `php phlag queue:work --sleep=1 --tries=3` implementation). The worker service intentionally omits any `ports` mapping because it communicates only with internal services.

Launch one or more worker containers with:

```bash
docker compose -f compose.yaml -f compose.workers.yaml up -d worker
docker compose -f compose.yaml -f compose.workers.yaml up -d --scale worker=3
```

Inspect their status and logs as they run:

```bash
docker compose -f compose.yaml -f compose.workers.yaml ps worker
docker compose -f compose.yaml -f compose.workers.yaml logs -f worker
```

Stop and remove the workers without touching the primary stack:

```bash
docker compose -f compose.yaml -f compose.workers.yaml stop worker
docker compose -f compose.yaml -f compose.workers.yaml rm -f worker
```

## Resource Planning

- **CPU & RAM:** Each PHP process competes for the Docker Desktop allocation. Increase the CPU (4→6 cores) and memory (4→8 GB) sliders when running more than two worker containers.
- **Database connections:** Confirm that `POSTGRES_MAX_CONNECTIONS` (set via `.env.local`) leaves headroom for the new workers; Laravel/PDO pools will open additional connections as they scale.
- **Redis throughput:** Monitor `docker compose logs redis` for `BUSY` or connection warnings. If caching becomes saturated, raise Redis’ memory limits or reduce worker concurrency.

Revisit these settings whenever you introduce new worker types so that local demos mimic your intended production scaling story.

## Cleanup Checklist

- Scale services back to a single instance (`docker compose up -d --scale app=1`).
- Remove optional worker containers (`docker compose -f compose.yaml -f compose.workers.yaml down`).
- Run `docker compose ps --all` to ensure no orphaned worker containers remain.

Document any new profiles or override files in ADRs when they become a permanent part of the stack.
