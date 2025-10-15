# 5. Adopt JWT Bearer Authentication

Date: 2025-10-10

## Status

Accepted

## Context

Phlag will expose APIs that need to be consumed by CLI tooling and, eventually, other service integrations. A lightweight, stateless authentication mechanism is needed to let the service validate requests without maintaining server-side session state. The platform will run in distributed environments (local development, CI, deployed hosting) where horizontally scaling instances should not share sticky session storage. The solution should also be easy to introspect during development and integrate with third-party tooling.

## Decision

Issue JSON Web Tokens (JWT) that clients send in the `Authorization: Bearer <token>` header. Tokens are signed using an asymmetric key pair provided via environment variables sourced from the deployment platform’s secrets manager, allowing services to verify signatures without contacting a central session store. Tokens will encode minimal identifying claims (subject, issued-at, expiry, roles) and respect short lifetimes; refresh workflows can be handled either by the CLI or future user-facing flows.

## Consequences

Positive

-   Stateless verification keeps the API horizontally scalable and easy to cache at the edge.
-   JWT is widely supported by HTTP clients, SDKs, and middleware; integration friction is low.
-   Encoded claims allow the API to make authorization decisions without additional lookups.
-   Applying the guard to the `/v1` HTTP bridge keeps project, environment, flag, and evaluation routes aligned with CLI expectations while enforcing bearer authentication everywhere sensitive state mutates.

Negative

-   Token revocation is non-trivial; requires rotation strategies or blacklisting infrastructure.
-   Signing keys demand careful operational security (storage, rotation, audit).
-   Larger payloads than opaque tokens; leaks expose readable claims even if signatures hold.
