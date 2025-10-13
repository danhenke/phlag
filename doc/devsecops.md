# DevSecOps Practices and Verification Guide

Date: 2025-10-11 (updated 2025-02-23)

Phlag’s supply-chain story is now centred on local builds. The GitHub Actions publishing pipeline and its GHCR integrations have been retired, so contributors create, inspect, and distribute container images from their own workstations. This guide captures the controls that remain in place, how to recreate the evidence previously emitted by CI, and the manual checkpoints we expect before sharing a build with teammates or stakeholders.

---

## Tooling Requirements

- Docker Engine 26.0+ with BuildKit enabled.
- `composer` (managed via `shivammathur/setup-php` for CI, installed locally for developers).
- `syft` (or `docker sbom`) for Software Bill of Materials generation.
- `grype` for vulnerability scanning of SBOMs or container images.
- `jq` for JSON inspection when you need to script checks.

Cosign and GitHub CLI are no longer required for provenance verification because we do not publish signed attestations.

---

## 1. Local Build Assurance

Run the full quality gate locally before exporting or handing off an image:

```bash
composer install
composer lint
composer stan
composer test
```

Build the runtime image with a traceable tag:

```bash
./scripts/docker-build-app --tag "phlag-app:local-$(git rev-parse --short HEAD)"
```

Use `docker inspect phlag-app:latest --format '{{.Id}}'` to capture the resulting image digest if you need to reference it in an ADR or issue.

---

## 2. Generating SBOMs and Running Scans

Produce an SBOM directly from Docker or Syft:

```bash
docker sbom phlag-app:latest --output syft-json > sbom.syft.json
# or
syft phlag-app:latest -o spdx-json > sbom.spdx.json
```

Scan the resulting SBOM (or the image itself) with Grype:

```bash
grype sbom:sbom.spdx.json
# or
grype phlag-app:latest
```

Record the scan date, tool version, and digest in your change notes so reviewers can reproduce the results.

---

## 3. Manual Provenance & Change Tracking

Without automated attestations, we rely on lightweight manual artifacts:

- Capture the image digest and build command in the issue or ADR describing the change.
- Store exported tarballs alongside a `CHECKSUMS` file generated with `shasum -a 256`.
- When sharing via a temporary registry, tag images with the short commit hash (`phlag-app:local-<sha>`) to preserve traceability back to source.
- Link to the QA workflow run (`.github/workflows/qa.yml`) that validated the commit before you cut the image.

Maintain these records in the repository (docs or ADRs) so we can audit releases retrospectively.

---

## 4. Framework Alignment

| Practice / Framework                       | Local Control                                                                                              | Notes                                                                                   |
| ------------------------------------------ | ---------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| **Shift-left linting & testing**           | `composer lint`, `composer stan`, and `composer test` executed locally and in the QA workflow             | Share command output in issues/PRs; failures block image distribution.                   |
| **Dependency transparency (NIST SSDF PW.8)** | SBOMs generated on demand via `docker sbom`/`syft` and stored with release notes                           | Attach SBOM artifacts to ADRs or share them alongside exported tarballs.                |
| **Vulnerability management (RV.1)**        | `grype` scans run against SBOMs or the built image prior to sharing                                       | Document scan date, tool version, and outcome.                                           |
| **Change management (ISO 27001 / SOC 2)**   | Image digest + commit hash recorded in issues/ADRs; tarball checksums retained for auditability           | Reference QA workflow run IDs and build commands.                                        |
| **Infrastructure hardening (CIS Docker)**  | Dockerfile linting via BuildKit `--progress=plain` warnings + manual review                               | Consider running `docker/docker-bench-security` periodically against local builds.       |

---

## 5. Next Steps

- Automate SBOM and vulnerability scans via a make/composer target to standardise evidence capture.
- Explore lightweight signing (`cosign sign --key cosign.key phlag-app:local-<sha>`) if we reintroduce shared registries.
- Introduce a doc template for recording digest, SBOM location, and QA run output whenever we share a build externally.

For questions or updates, create an issue tagged `devsecops` in this repository.
