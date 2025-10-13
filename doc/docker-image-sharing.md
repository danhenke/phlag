# Docker Image Sharing Workflow

Date: 2025-10-11  
Related issue: #33

Some contributors prefer receiving pre-built Docker images instead of rebuilding the `app` service locally. This guide explains two supported sharing flows:

- Exporting a locally built image so another teammate can load it.
- Publishing an image to a container registry (for example GitHub Container Registry).

The multi-stage Dockerfile compiles the Laravel Zero application into a PHAR (`/app/phlag`) and ships only that artifact plus the production `public/` assets in the final image. When you run the container without bind mounts, `public/index.php` automatically boots from the bundled PHAR so the HTTP health endpoint (and any future web entrypoints) stay operational.

Both approaches produce an image compatible with the `app` service defined in `compose.yaml`. The stack now pulls `${PHLAG_APP_IMAGE:-ghcr.io/danhenke/phlag:latest}` automatically, so sharing an image primarily helps teammates who are offline or need to test an unpublished build. The CI workflow publishes multi-architecture images (`linux/amd64` and `linux/arm64`), letting Docker Compose choose the right variant for each host without extra configuration.

## Prerequisites

- Docker Engine 26.0+ and Compose plugin 2.27+ (see the main README for installation guidance).
- A successful `composer install` to ensure vendor assets are present before the image is built.
- For registry pushes, access to a registry namespace (for example `ghcr.io/<org>/phlag`) and a personal access token with the required scope (`write:packages` for GHCR).

## Build a custom application image (optional)

The default Compose stack pulls `${PHLAG_APP_IMAGE:-ghcr.io/danhenke/phlag:latest}`. When you need to share work-in-progress changes or support offline teammates, build a custom tag from the local Dockerfile:

```bash
CUSTOM_TAG="$(git rev-parse --short HEAD)"
./scripts/docker-build-app --tag "ghcr.io/danhenke/phlag:${CUSTOM_TAG}"
```

The helper wraps `docker buildx` and produces both the provided tag and `phlag-app:latest`. Prefer to call Docker directly? Run:

```bash
docker build -f Dockerfile -t "ghcr.io/danhenke/phlag:${CUSTOM_TAG}" .
```

After the build completes, point Compose at your tag by exporting `PHLAG_APP_IMAGE` before running `docker compose`:

```bash
export PHLAG_APP_IMAGE="ghcr.io/danhenke/phlag:${CUSTOM_TAG}"
```

Unset the variable when you want to return to the published `latest` image.

## Option 1: Share via image archive

When teammates are working offline or without registry access, export the built image:

```bash
docker save "ghcr.io/danhenke/phlag:${CUSTOM_TAG}" -o phlag-app.tar
```

Distribute `phlag-app.tar` (for example via AirDrop or a shared drive). Recipients load the image and can optionally retag it:

```bash
docker load --input phlag-app.tar
# Optional: align any custom tag shared out-of-band
docker tag "ghcr.io/danhenke/phlag:${CUSTOM_TAG}" "ghcr.io/danhenke/phlag:latest"
```

After loading, export `PHLAG_APP_IMAGE` (or retag to the default `latest`) so Compose reuses the shared layers:

```bash
export PHLAG_APP_IMAGE="ghcr.io/danhenke/phlag:${CUSTOM_TAG}"
docker compose up -d
```

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

    Alternatively, reuse the helper to tag and push in one step:

    ```bash
    ./scripts/docker-publish-app --image "ghcr.io/${GITHUB_USERNAME}/phlag" --tag "$(git rev-parse --short HEAD)" --latest
    ```

4. Share the tag with teammates. They can pull and keep both the remote tag and the local Compose tag:

    ```bash
    docker pull "$IMAGE_TAG"
    export PHLAG_APP_IMAGE="$IMAGE_TAG"
    ```

Keeping an exported `PHLAG_APP_IMAGE` (or retagging to `ghcr.io/danhenke/phlag:latest`) ensures `docker compose up` reuses the shared image.

## Updating the Compose stack to consume shared images

Compose reads the app image from the `PHLAG_APP_IMAGE` environment variable. Export it in your shell before launching the stack:

```bash
export PHLAG_APP_IMAGE="ghcr.io/<org>/phlag:<tag>"
docker compose up -d
```

Unset the variable (or remove it from your shell configuration) to fall back to the published `ghcr.io/danhenke/phlag:latest` image.

Prefer storing the override in version-controlled automation? Create a small Compose overlay that pins the image:

```yaml
# compose.override.yaml
services:
  app:
    image: ghcr.io/<org>/phlag:<tag>
```

Run Compose with both files when you need the custom image:

```bash
docker compose -f compose.yaml -f compose.override.yaml up -d
```

Remove or ignore the override when returning to the default image.

## Automated GitHub Actions publish

The workflow in `.github/workflows/ci.yml` builds the application image and publishes it to GitHub Container Registry (GHCR).

- Triggers on `workflow_dispatch` (with an optional tag input) and on Git tags that match `v*`.
- Builds with `docker buildx` checks enabled, emits SBOMs and provenance attestations (`provenance: mode=max`), and exports deterministic layers by setting `SOURCE_DATE_EPOCH` to the latest commit timestamp.
- Generates semver (`major`, `major.minor`, `version`) and `sha` tags via `docker/metadata-action`, and applies matching labels/annotations to the image and attestations.
- Authentication uses the built-in `GITHUB_TOKEN`, which is sufficient for publishing to this repository's GHCR namespace; no additional secrets are required.

To run the workflow manually:

1. Navigate to **Actions → CI → Run workflow**.
2. Provide a tag (optional) or accept the default short SHA tag.
3. Confirm you have permission to publish to the repository namespace (the workflow uses `GITHUB_TOKEN` automatically).

After the run completes you can download SBOM and provenance artifacts from the workflow summary. The published image is available at:

```bash
docker pull ghcr.io/<owner>/phlag:<tag>
docker tag ghcr.io/<owner>/phlag:<tag> phlag-app:latest
```
