# 2. Adopt Trunk-Based Development Workflow

Date: 2025-10-10

## Status

Superseded by [ADR-0009](0009-adopt-github-flow-for-codex-cloud-collaboration.md)

## Context

This project is a solo, education-focused effort intended to demonstrate modern PHP practices without the coordination overhead that multi-person teams face. Feature-branch workflows such as GitHub Flow or GitLab Flow introduce pull-request ceremonies and long-lived branches that add little value when a single maintainer is iterating quickly. Keeping the default branch releasable at all times also mirrors the target deployment story (local Docker Compose) where shipping is synonymous with running the current code locally.

## Decision

Develop directly on the `main` branch using a trunk-based workflow. Commits remain small and incremental, each gated locally by Composer checks (`composer lint`, `composer stan`, `composer test`). Short-lived branches are permitted for experiments but must be merged or discarded the same day. Tags are created sparingly to mark notable milestones (e.g., demo releases).

## Consequences

Positive

-   Simplifies the delivery loop and keeps focus on learning outcomes instead of branch management overhead.
-   Reduces integration friction by ensuring every change lands on `main` quickly and can be run locally via Docker Compose.
-   Encourages small, frequent commits that document progress and remain easy to revert using standard Git tooling.
-   Aligns with the GitHub Actions pipeline, which always tests the latest `main` revision without needing branch-specific configuration.

Negative

-   Provides limited rehearsal for collaborative review practices (pull requests, approvals) that exist on larger teams.
-   Requires personal discipline to keep `main` healthy without enforced server-side CI gates or protected-branch rules.
-   Makes multi-feature parallel development harder if project scope expands beyond a single maintainer; the workflow would need re-evaluation.
