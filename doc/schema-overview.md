# Schema Overview

Date: 2025-10-11

This document captures the current relational model for Phlag based on the Laravel migrations in `database/migrations`. Use it to orient yourself when extending the domain or crafting new database queries.

```mermaid
erDiagram
    PROJECTS {
        uuid id PK
        string key UK
        string name
        text description
        json metadata
        timestamptz created_at
        timestamptz updated_at
    }

    ENVIRONMENTS {
        uuid id PK
        uuid project_id FK
        string key
        string name
        text description
        boolean is_default
        json metadata
        timestamptz created_at
        timestamptz updated_at
    }

    FLAGS {
        uuid id PK
        uuid project_id FK
        string key
        string name
        text description
        boolean is_enabled
        json variants
        json rules
        timestamptz created_at
        timestamptz updated_at
    }

    EVALUATIONS {
        uuid id PK
        uuid project_id FK
        uuid environment_id FK
        uuid flag_id FK
        string flag_key
        string variant
        string evaluation_reason
        string user_identifier
        json request_context
        json evaluation_payload
        timestamptz evaluated_at
        timestamptz created_at
        timestamptz updated_at
    }

    AUDIT_EVENTS {
        uuid id PK
        uuid project_id FK
        uuid environment_id FK
        uuid flag_id FK
        string action
        string target_type
        uuid target_id
        string actor_type
        string actor_identifier
        json changes
        json context
        timestamptz occurred_at
        timestamptz created_at
        timestamptz updated_at
    }

    API_CREDENTIALS {
        uuid id PK
        uuid project_id FK
        uuid environment_id FK
        string name
        string key_hash UK
        jsonb roles
        boolean is_active
        timestamptz expires_at
        timestamptz created_at
        timestamptz updated_at
    }

    PROJECTS ||--o{ ENVIRONMENTS : hosts
    PROJECTS ||--o{ FLAGS : owns
    PROJECTS ||--o{ EVALUATIONS : records
    PROJECTS ||--o{ API_CREDENTIALS : authenticates
    ENVIRONMENTS ||--o{ EVALUATIONS : scopes
    ENVIRONMENTS ||--o{ API_CREDENTIALS : secures
    FLAGS ||--o{ EVALUATIONS : produces
    PROJECTS ||--o{ AUDIT_EVENTS : audits
    ENVIRONMENTS }o..o{ AUDIT_EVENTS : contextualizes
    FLAGS }o..o{ AUDIT_EVENTS : touches
```

## Relationship Notes

- `environments`, `flags`, and `evaluations` cascade on project deletion; derived records disappear with the parent project.
- Evaluations belong to a single environment/flag pair and capture request metadata for debugging.
- Audit events may reference a project, environment, and/or flag (all optional) to describe the scope of the change.
- API credentials store SHA-256 hashes of project/environment API keys so only hashed material lands in Postgres. Seeders mint demo credentials only when `PHLAG_DEMO_API_KEY` is defined. Tokens cannot be issued when a credential is inactive or past its optional expiration.
- API credential records include a human-friendly name and optional role list; permissions are resolved from these roles at runtime when issuing JWTs.

## Keeping the Diagram Updated

1. Review new or modified migrations under `database/migrations`.
2. Update the entity attributes or relationships in the Mermaid definition above.
3. Preview the diagram locally (e.g., VS Code Markdown preview or Mermaid Live Editor) before committing.

When introducing new tables, add them to the diagram and describe any cascading behavior or key constraints so future contributors can reason about the schema quickly.
