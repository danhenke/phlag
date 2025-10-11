# Docker Environment Requirements & Troubleshooting

Date: 2025-10-10

This guide records the minimum Docker versions supported by Phlag and provides quick fixes for the issues contributors most frequently encounter while working with the local Docker Compose stack.

## Minimum Supported Versions

| Platform | Minimum Version | Notes |
| --- | --- | --- |
| Docker Desktop (macOS / Windows) | 4.32.0 (Engine 26.1.1, Compose 2.27+) | Ships BuildKit 0.12+, required for the `# syntax=docker/dockerfile:1.7` directive used by `docker/app/Dockerfile`. Enable the WSL 2 backend on Windows. |
| Linux | Docker Engine 26.0.0+ with Compose plugin 2.27+ | Install the `docker-compose-plugin` package so `docker compose` is available. Ensure BuildKit is enabled (`DOCKER_BUILDKIT=1`). |

Verify your installation before running `docker compose up`:

```bash
docker version --format '{{.Server.Version}}'
docker compose version --short
```

Both reported versions should meet or exceed the minimums above. If they do not, upgrade Docker Desktop or your Engine/Compose packages.

## Fast Health Checks

- `docker info` — confirms the engine is running and reports the virtualization backend (WSL 2, HyperKit, etc.).
- `docker compose ls` — ensures the Compose plugin is installed and can communicate with the daemon.
- `docker system df` — verifies sufficient disk space is available for the PHP / PostgreSQL / Redis images.

## Troubleshooting Common Failures

### Build errors referencing the Dockerfile syntax version

**Symptoms**
- `failed to solve with frontend dockerfile.v1: failed to create LLB definition`
- `unknown flag: mount` or `failed to parse Dockerfile: syntax docker/dockerfile:1.7 not supported`

**Fix**
1. Upgrade to the minimum versions listed above.
2. Ensure BuildKit is enabled by exporting `DOCKER_BUILDKIT=1` or setting `"features": { "buildkit": true }` in `~/.docker/config.json`.
3. Retry `docker compose build app`.

### `docker compose up` fails because port 80 is already in use

**Symptoms**
- `Error starting userland proxy: listen tcp 0.0.0.0:80: bind: address already in use`

**Fix**
1. Identify the conflicting process: `sudo lsof -i :80`.
2. Stop the process (local web server, AirPlay, IIS, etc.).
3. Re-run `docker compose up -d --build`. As a fallback for demos, override the port with `APP_HTTP_PORT=8080 docker compose up` and visit `http://localhost:8080/`.

### PostgreSQL refuses to start due to a mismatched schema

**Symptoms**
- Logs report `database files are incompatible with server` or migration errors persist across restarts.

**Fix**
1. Stop the stack: `docker compose down`.
2. Remove the cached volume: `docker volume rm phlag_pgdata`.
3. Start fresh: `docker compose up -d --build` followed by `./scripts/app-migrate --seed`.

### Windows-specific: WSL 2 backend is disabled

**Symptoms**
- Docker Desktop displays `WSL kernel version too old` or asks to enable virtualization.

**Fix**
1. From PowerShell (admin), run `wsl --update` followed by `wsl --set-default-version 2`.
2. Ensure the `Virtual Machine Platform` Windows feature is enabled.
3. Restart Docker Desktop, then retry `docker compose up -d --build`.

### Containers start but CLI commands fail to read environment variables

**Symptoms**
- `docker compose exec app php phlag ...` exits with missing database credentials.

**Fix**
1. Confirm `.env.local` exists and contains the values documented in `README.md`.
2. Export the variables in your shell before calling the helper scripts:

```bash
set -a; source .env.local; set +a
```

3. Re-run the desired Laravel Zero command (e.g., `./scripts/app-migrate`).

## When to Ask for Help

If the guidance above does not resolve the issue, capture:

- Output of `docker version`, `docker compose version`, and `docker info --format '{{json .}}'`.
- Logs from the failing service (`docker compose logs app`, `postgres`, or `redis`).
- Host OS version and hardware details (chipset, virtualization support).

Share that data in the GitHub issue or discussion so maintainers can suggest next steps without guesswork.
