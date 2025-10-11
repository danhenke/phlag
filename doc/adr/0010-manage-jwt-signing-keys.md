# 10. Manage JWT signing keys and rotation

Date: 2025-10-11

## Status

Accepted

## Context

Architecture Decision Record 5 established JSON Web Tokens (JWT) as the authentication mechanism for Phlag. The platform now needs an explicit strategy for generating, storing, and rotating the signing material that backs those tokens. API consumers should be able to validate signatures without calling back to the service, and operators must rotate keys without invalidating every outstanding token. Local development also needs a simple way to bootstrap credentials without the operational ceremony required in hosted environments.

## Decision

-   Use RSA (RS256) key pairs for hosted environments. The active private key is injected via environment variables populated by Doppler: `JWT_KEY_ID`, `JWT_PRIVATE_KEY` (PEM-encoded), and `JWT_PUBLIC_KEY`. Tokens embed the `kid` header so verifiers can select the correct public key.
-   Support phased rotation by accepting a secondary public key via `JWT_PREVIOUS_KEY_ID` and `JWT_PREVIOUS_PUBLIC_KEY`. The API signs new tokens with the active key while still validating signatures created by the previous key until their TTL expires.
-   Provide a Laravel Zero command (`projects:key:rotate`) that generates a new RSA pair, updates Doppler secrets, and emits rotation guidance. Rotation follows a three-step runbook: create new key pair, deploy with both public keys present, then remove the previous key once old tokens expire.
-   Retain `JWT_SECRET` as the development fallback. When RSA keys are absent, the service signs tokens with the symmetric secret defined in `.env.local` so contributors avoid managing PEM files; this path never runs outside local tooling.

## Consequences

Positive

-   Explicit rotation workflow keeps JWT authentication aligned with security best practices without forcing mass logout events.
-   Publishing the public key lets future SDKs or edge caches validate tokens without proxying to the API.
-   Local onboarding stays lightweight while production deployments gain stronger guarantees.

Negative

-   Managing multi-line PEM values in environment variables is error-prone; Doppler templates and CI secrets must preserve formatting.
-   The application must load both RSA and HMAC dependencies to support the dual-mode signing path and clear telemetry around which mode is active.
-   Operational playbooks must call out how quickly to promote the new key and retire the previous one to avoid drift.
