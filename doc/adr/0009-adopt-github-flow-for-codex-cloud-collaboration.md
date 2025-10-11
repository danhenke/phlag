# 9. Adopt GitHub Flow for Codex Cloud Collaboration

Date: 2025-10-11

## Status

Accepted  
Supersedes: [ADR-0002](0002-adopt-trunk-based-development-workflow.md)

## Context

Codex Cloud tasks and reviews assume a GitHub Flow style lifecycle where work starts on a feature branch, is validated in a pull request, and only then merges to the default branch. Remaining on a trunk-based workflow forces the assistant and maintainers to work directly on `main`, complicating automated previews, policy enforcement, and review checkpoints. Coordinating with external contributors also benefits from the predictable branch naming and pull request history that GitHub Flow produces.

## Decision

Adopt GitHub Flow for day-to-day development. All work begins from an up-to-date `main`, proceeds on a short-lived, issue-linked branch, and lands through a pull request that captures validation evidence. Codex Cloud automation and human reviewers use pull requests as the canonical review surface. After approval, branches merge to `main` using a fast-forward or squash merge, and the branch is deleted.

## Consequences

Positive

-   Aligns the development workflow with Codex Cloud expectations, unlocking branch-based automations and review tooling.
-   Encourages collaborative review habits, durable PR summaries, and consistent validation notes before merging.
-   Provides clearer traceability across issues, branches, and deployment checkpoints for future contributors.
-   Preserves `main` as a stable integration point while still supporting rapid iteration through small branches.

Negative

-   Introduces additional ceremony (branch creation, pull request management) even for solo or low-risk changes.
-   Requires discipline to keep branches short-lived and rebased, especially when multiple efforts run in parallel.
-   Adds overhead when experimenting quickly, as proof-of-concept work must still route through pull requests before landing.
