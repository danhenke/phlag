# Docker Image Sharing Workflow

Date: 2025-10-11  
Related issue: #33

Some contributors prefer receiving pre-built Docker images instead of rebuilding the `app` service locally. This guide explains two supported sharing flows:

- Exporting a locally built image so another teammate can load it.
- Publishing an image to a container registry (for example GitHub Container Registry).

Both approaches produce an image compatible with the `app` service defined in `compose.yaml`.

## Prerequisites

- Docker Engine 26.0+ and Compose plugin 2.27+ (see the main README for installation guidance).
- A successful `composer install` to ensure vendor assets are present before the image is built.
- For registry pushes, access to a registry namespace (for example `ghcr.io/<org>/phlag`) and a personal access token with the required scope (`write:packages` for GHCR).

## Build the application image

From the project root, build the `app` service image that Compose normally generates on demand:

```bash
docker compose build app
```

This produces the image `phlag-app:latest` in your local Docker cache (Compose names the image `<project>-<service>` by default).

If you prefer to bake a source control revision into the tag before sharing, retag the image after the build:

```bash
IMAGE_TAG="phlag-app:local-$(git rev-parse --short HEAD)"
docker tag phlag-app:latest "$IMAGE_TAG"
```

Keep the `phlag-app:latest` tag so the Compose stack can continue to use it locally.

## Option 1: Share via image archive

When teammates are working offline or without registry access, export the built image:

```bash
docker save phlag-app:latest -o phlag-app.tar
```

Distribute `phlag-app.tar` (for example via AirDrop or a shared drive). Recipients load the image and can optionally retag it:

```bash
docker load --input phlag-app.tar
# Optional: align any custom tag shared out-of-band
docker tag phlag-app:latest phlag-app:shared-$(date +%Y%m%d)
```

After loading, they can run `docker compose up -d` without triggering a rebuild.

## Option 2: Publish to a registry

Publishing allows teammates to pull the image whenever they need it. The example below uses GitHub Container Registry (GHCR); adjust the registry hostname and scopes as required.

1. Authenticate once per session:

    ```bash
    echo "${GHCR_TOKEN}" | docker login ghcr.io -u "${GITHUB_USERNAME}" --password-stdin
    ```

2. Choose a consistent tag. We recommend including the Git commit for traceability:

    ```bash
    IMAGE_TAG="ghcr.io/${GITHUB_USERNAME}/phlag:$(git rev-parse --short HEAD)"
    docker tag phlag-app:latest "$IMAGE_TAG"
    ```

3. Push the image:

    ```bash
    docker push "$IMAGE_TAG"
    ```

4. Share the tag with teammates. They can pull and keep both the remote tag and the local Compose tag:

    ```bash
    docker pull "$IMAGE_TAG"
    docker tag "$IMAGE_TAG" phlag-app:latest
    ```

Keeping `phlag-app:latest` alongside the remote tag ensures `docker compose up` reuses the shared image.

## Updating the Compose stack to consume shared images

The default workflow (`docker compose up --build`) will always rebuild the image. When you want to use a shared image, omit the `--build` flag:

```bash
docker compose up -d
```

If you need to force Compose to use a registry tag directly, add an override file that declares the image explicitly:

```yaml
# compose.override.yaml
services:
  app:
    image: ghcr.io/<org>/phlag:<tag>
    build: null
```

Run Compose with the override when you want to skip the local build:

```bash
docker compose -f compose.yaml -f compose.override.yaml up -d
```

Remember to remove the override file when you return to local development so subsequent changes rebuild the image as expected.
