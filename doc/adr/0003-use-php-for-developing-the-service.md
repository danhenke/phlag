# 3. Use PHP for developing the service

Date: 2025-10-10

## Status

Accepted

## Context

Phlag exists to demonstrate modern PHP engineering practices in a realistic-yet-contained environment. The maintainer’s professional background is anchored in PHP ecosystems, and the supporting tooling (Laravel Zero, Pest, PHPStan) align with the learning objectives for the project. Alternatives such as Node.js or Go would showcase different ecosystems but would dilute the focus on communicating PHP fluency to reviewers.

## Decision

Adopt PHP 8.4 as the implementation language for all application code. The runtime will be installed via Composer’s platform requirements, and the codebase will embrace modern PHP constructs (typed properties, enums, readonly classes, attributes). Supporting libraries—Laravel Zero, Pest, PHPStan, swagger-php—are chosen to emphasise contemporary PHP workflows.

## Consequences

Positive

-   Positions the project squarely as evidence of current PHP expertise, matching the portfolio goal.
-   Leverages the extensive Laravel ecosystem for CLI scaffolding, configuration, and community-supported packages.
-   Enables reuse of familiar tooling (Composer, Pest, PHPStan) that integrates smoothly with GitHub Actions and Docker-based development.

Negative

-   Limits opportunities to contrast PHP implementations with other languages or frameworks, which might interest broader audiences.
-   Requires contributors to be comfortable with PHP tooling; cross-language collaborators may face a learning curve.
-   PHP’s single-threaded nature means background processing or high-concurrency demos require additional components (queues, workers) if showcased later.
