# 13. Introduce role-based access control for projects and environments

Date: 2025-10-16

## Status

Accepted

## Context

The platform previously treated JWT “roles” as ad-hoc scopes stored on each API credential. Route middleware compared those strings directly, forcing operators to reason about eight granular permissions every time they issued a key. The CLI mirrored that exposure, prompting for comma-separated scopes and offering no guardrails beyond string validation. As feature work expands—projects, environments, flags, cache warmers—we need clearer role definitions, centralized permission management, and a migration story that preserves existing credentials without manual data fixes.

## Decision

-   Define role and permission catalogs under `config/rbac.php`. Three coarse-grained roles ship initially: `project.viewer`, `environment.operator`, and the default `project.maintainer`, each mapping to their required permissions.
-   Introduce an application-level `RoleRegistry` to expose role metadata, normalize assignments, and resolve permissions for both the HTTP middleware and CLI tooling.
-   Replace the scope-checking middleware with `AuthorizeTokenPermissions`, which derives granted permissions from JWT claims (and the registry) before enforcing access on routes, ensuring future role updates automatically flow through.
-   Update the token exchange service so issued JWTs include both role identifiers and a flattened permission list, keeping downstream clients aware of their capabilities.
-   Tweak the `api-key:create` command and seeders to work with roles, providing friendlier prompts, validation, and default assignments that align with the new RBAC model.
-   Add a migration that copies existing `scopes` values into the new `roles` column, infers appropriate role bundles, and removes the obsolete column to keep the schema consistent going forward.

## Consequences

Positive

-   Operators now pick from a small, well-documented set of roles, reducing mistakes when provisioning credentials.
-   Centralizing role definitions simplifies future enhancements (e.g., new permissions) without sweeping route changes.
-   JWT consumers gain clarity by inspecting both role labels and effective permissions, aiding audit and observability.

Negative

-   Existing credentials migrate according to heuristics (e.g., any manage scope becomes `project.maintainer`); unusual custom scope mixes may need manual review.
-   Additional indirection (registry + derived permissions) slightly increases middleware complexity.

## Migration

-   Deploy the new migration (`2025_10_16_000008_introduce_roles_to_api_credentials.php`) to add the `roles` column, backfill data from `scopes`, and drop the legacy field.
-   Regenerate JWTs for long-lived clients so tokens carry both role IDs and derived permissions; expired tokens minted before this change will continue to fail authorization gracefully.
-   Update documentation and operator runbooks to reference roles instead of raw scopes, ensuring future credentials use the intended RBAC profiles.
