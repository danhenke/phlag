# Docker Image Sharing Workflow

Date: 2025-10-11  
Related issue: #33

Phlag now ships as a locally built container image. `docker compose up -d --build` compiles the runtime from the Dockerfile and tags the result as `${PHLAG_APP_IMAGE:-phlag-app:latest}`. When teammates cannot rebuild the image themselves—offline travel, limited bandwidth, or a locked-down workstation—you can hand off a build artifact directly. This note captures two lightweight ways to do that without relying on an external registry.

## Prerequisites

- Docker Engine 26.0+ and Compose plugin 2.27+ (see the main README for installation guidance).
- A successful `composer install` so vendor assets exist before you build the image.
- Disk space for temporary archives (expect 250–300 MB per exported image).

## Build a custom application image (optional)

Compose already builds `phlag-app:latest` for you, but tagging the image with the commit hash makes handoffs clearer:

```bash
CUSTOM_TAG="$(git rev-parse --short HEAD)"
./scripts/docker-build-app --tag "phlag-app:local-${CUSTOM_TAG}"
```

The helper wraps `docker buildx` and produces both the provided tag and `phlag-app:latest`. Prefer to use Docker directly? Run:

```bash
docker build -f Dockerfile -t "phlag-app:local-${CUSTOM_TAG}" .
docker tag "phlag-app:local-${CUSTOM_TAG}" phlag-app:latest
```

Export the tag so Compose uses it on your machine:

```bash
export PHLAG_APP_IMAGE="phlag-app:local-${CUSTOM_TAG}"
```

Unset the variable when you want to fall back to the default `phlag-app:latest`.

## Option 1: Share via image archive

When teammates are working offline or without Docker build tools, export the image and hand them the archive:

```bash
docker save "phlag-app:local-${CUSTOM_TAG}" -o "phlag-app-${CUSTOM_TAG}.tar"
```

Distribute the tarball via AirDrop, a shared drive, or another secure channel. Recipients load the image and retag it for Compose:

```bash
docker load --input "phlag-app-${CUSTOM_TAG}.tar"
docker tag "phlag-app:local-${CUSTOM_TAG}" phlag-app:latest
export PHLAG_APP_IMAGE="phlag-app:local-${CUSTOM_TAG}"
docker compose up -d
```

Remind them to remove the archive after loading to reclaim disk space.

## Option 2: Share a local registry snapshot

If you and your teammate are on the same network, you can share the image directly without creating a tarball by using the [Docker registry container](https://hub.docker.com/_/registry):

```bash
# On the host that already built the image
docker run -d --name phlag-registry -p 5000:5000 registry:2
docker tag "phlag-app:local-${CUSTOM_TAG}" localhost:5000/phlag-app:${CUSTOM_TAG}
docker push localhost:5000/phlag-app:${CUSTOM_TAG}
```

Your teammate pulls from the temporary registry:

```bash
docker pull localhost:5000/phlag-app:${CUSTOM_TAG}
docker tag localhost:5000/phlag-app:${CUSTOM_TAG} phlag-app:latest
export PHLAG_APP_IMAGE="phlag-app:local-${CUSTOM_TAG}"
```

Stop the registry when the transfer is complete:

```bash
docker container rm -f phlag-registry
```

The local registry never leaves your machine and avoids publishing to external services.

## Updating the Compose stack to consume shared images

Compose reads the app image from the `PHLAG_APP_IMAGE` environment variable. Export it in your shell before launching the stack:

```bash
export PHLAG_APP_IMAGE="phlag-app:local-${CUSTOM_TAG}"
docker compose up -d
```

Prefer storing the override in version-controlled automation? Create a small Compose overlay that pins the tag:

```yaml
# compose.override.yaml
services:
  app:
    image: phlag-app:local-${CUSTOM_TAG}
```

Run Compose with both files whenever you need the shared image:

```bash
docker compose -f compose.yaml -f compose.override.yaml up -d
```

Remove or ignore the override when returning to the default `phlag-app:latest`.
