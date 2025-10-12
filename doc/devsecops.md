# DevSecOps Practices and Verification Guide

Date: 2025-10-11

This guide documents how Phlag’s build pipeline supports modern DevSecOps practices, and how to consume the resulting artifacts (annotations, attestations, and SBOMs). It also maps our controls to industry frameworks such as NIST SSDF (SP 800-218), SLSA, the CIS Docker/Kubernetes Benchmarks, and ISO 27001 change & asset management requirements.

---

## 1. Accessing GitHub Actions Annotations

Build checks surface inline annotations for lint, static analysis, and docker build checks.

1. Navigate to **Actions → CI → Latest run**.
2. Open the failing job (for example **docker**).  
3. Select the step (e.g. “Build and push”) and click **Annotations** in the right rail.
4. Each annotation links directly to the offending file/line in the commit.

> **Tip:** annotations are only preserved when the workflow runs with `checks: write` permission (already configured in `.github/workflows/ci.yml`).

---

## 2. Verifying Provenance Attestations

The CI workflow publishes provenance attestations (`provenance: mode=max`) with build metadata.

### Requirements

- [`cosign`](https://github.com/sigstore/cosign) v2.0+  
- Access to the GitHub Container Registry (GHCR) image `ghcr.io/danhenke/phlag`.

### Steps

```bash
# Pull the most recent tag from CI (replace with desired tag if needed)
TAG=$(git rev-parse --short HEAD)

# Verify SLSA provenance attestation
cosign verify-attestation \
  ghcr.io/danhenke/phlag:${TAG} \
  --type slsaprovenance \
  --certificate-identity "https://github.com/danhenke/phlag/.github/workflows/ci.yml@refs/heads/main" \
  --certificate-oidc-issuer "https://token.actions.githubusercontent.com"
```

Expected output includes the BuildKit provenance payload showing:
- builder image (`moby/buildkit`),
- reproducible timestamps via `SOURCE_DATE_EPOCH`,
- command line checks (`call=check`), and
- materials (git commit SHA).

---

## 3. Querying the SBOM

The workflow emits SBOMs (in SPDX JSON) during the `docker/build-push-action@v6` run.

### Option A: Download from the workflow run
1. Open the CI run.
2. Under **Artifacts**, download the `sbom` archive.
3. Inspect with [`syft`](https://github.com/anchore/syft) or `jq`:
   ```bash
   syft packages sbom.spdx.json
   ```

### Option B: Pull directly from GHCR Attestations (requires cosign v2.1+)
```bash
cosign download sbom ghcr.io/danhenke/phlag:${TAG} > sbom.spdx.json
jq '.packages[] | {name, versionInfo, supplier}' sbom.spdx.json | head
```

Use the SBOM to drive vulnerability scanning (`grype sbom:sbom.spdx.json`) or license compliance lists.

---

## 4. Shift-Left Security & Continuous Assurance

| Practice | Implementation |
| --- | --- |
| **Shift-left linting/static analysis** | `composer lint`, `composer stan`, and `composer test` executed in CI before builds; developers run the same commands locally. |
| **Immutable & reproducible builds** | `SOURCE_DATE_EPOCH` set to git commit timestamp; BuildKit produces deterministic layers and provenance. |
| **Dependency transparency** | SBOM published for every build and stored in GHCR attestations. |
| **Policy enforcement** | Docker build checks (`call: check`) fail the build if Dockerfile best practices break. |
| **Continuous assurance** | GHCR image metadata (labels/annotations) include commit SHA, semver, and OCI description for downstream traceability. |
| **Credential minimisation** | Builds authenticate with `GITHUB_TOKEN`; no long-lived registry credentials are stored. |

### Responding to CVE Events Using the SBOM
1. **Identify affected packages** using the SBOM or a scanner (`grype sbom:sbom.spdx.json`).
2. **Locate builds** by querying GHCR metadata (`docker buildx imagetools inspect ghcr.io/danhenke/phlag:${TAG}`).
3. **Patch** dependencies, commit changes, and rerun CI to produce a new SBOM/provenance.
4. **Document** the response (issue/ADR) and link to the attestation verifying the patched build.

---

## 5. Framework Alignment

| Framework | Relevant CI/CD Controls | Notes |
| --- | --- | --- |
| **NIST SSDF (SP 800-218)** | PW.8 (Generate and analyze SBOMs), RV.1 (Perform threat/vuln analysis), PS.3 (Protect build infrastructure) | SBOM + provenance supply chain evidence; BuildKit checks enforce secure builds. |
| **SLSA** | Level 2 (automated build, authenticated provenance), progressing toward Level 3 | Provenance attestations via BuildKit + GitHub OIDC satisfy SLSA 2 attestations. |
| **CIS Docker Benchmarks** | Lint checks catch Dockerfile anti-patterns; reproducible builds help validate image content | Recommend running `docker/docker-bench-security` periodically against the image. |
| **CIS Kubernetes Benchmarks** | Not yet in scope; use the SBOM & provenance when deploying to clusters to support admission policies. |
| **ISO 27001 (Change & Asset Management)** | OCI labels + provenance act as immutable change records; SBOM documents asset composition | Link SBOM artifacts to change tickets to demonstrate controlled rollout. |

---

## 6. Next Steps

- Integrate automated SBOM vulnerability scanning (e.g. `grype`) into CI for continuous monitoring.
- Consider publishing attestation digests to a transparency log (e.g. Rekor) for additional tamper evidence.
- Expand metadata annotations to include documentation URLs or hash of infrastructure manifests once available.

For questions or updates, create an issue tagged `devsecops` in this repository.
