# 4. Adopt Laravel Zero as the Application Framework

Date: 2025-10-10

## Status

Accepted

## Context

This project is a modern PHP demonstration intended to highlight current engineering practices, code quality, and framework selection discipline.

Laravel Zero was evaluated as a micro-framework foundation for building command-line or lightweight service applications that still leverage the broader Laravel ecosystem. Its design focuses on minimalism, composability, and maintainability — making it well suited for small-to-medium, single-purpose services such as a feature-flag management API.

## Decision

We will use Laravel Zero as the foundation framework for the PHP service.

## Consequences

Positive

-   Lightweight runtime tailored for API or CLI-style microservices.
-   Consistent developer experience for engineers familiar with Laravel.
-   Facilitates clear separation of concerns and maintainable module structure.

Negative

-   Appears to have a smaller community than full Laravel.
-   Not sure if all Laravel packages work seamlessly with Laravel Zero.
-   Adds a small learning curve being previously unfamiliar with Laravel Zero.
