# SaaS Boilerplate

> The open-source multi-tenant SaaS starter kit built with Laravel 12, modular DDD features, and production-ready billing, compliance, and security tooling.

## Table of Contents
- [Overview](#overview)
- [Feature Highlights](#feature-highlights)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Getting Started](#getting-started)
  - [Manual installation](#manual-installation)
  - [Docker with Laravel Sail](#docker-with-laravel-sail)
- [Environment & Configuration](#environment--configuration)
- [Developer Workflow](#developer-workflow)
- [Background Jobs & Scheduling](#background-jobs--scheduling)
- [Testing](#testing)
- [API Reference](#api-reference)
- [Contributing](#contributing)
- [License](#license)
- [Community](#community)

## Overview

SaaS Boilerplate is a Laravel 12 application that packages the recurring building blocks of a multi-tenant subscription product: tenant-aware API endpoints, flexible roles and permissions, Stripe-powered billing, compliance workflows, and developer-friendly tooling. The codebase follows a modular Domain-Driven Design (DDD) approach, where each capability lives under `src/Features`, and shared infrastructure such as tenancy, rate limiting, and security middleware is isolated under `src/Infrastructure`.

The project is maintained as an open-source reference implementation. You are encouraged to fork it, adapt it to your domain, and contribute improvements back to the community.

## Feature Highlights

**Tenancy & Identity**
- Organization isolation handled through a tenant context (`SetTenantContext` middleware) and the `X-Organization-Id` header.
- Invitation lifecycle (send, accept, reject, cancel) with email notifications and status tracking.
- Organization settings (locale, timezone, currency) with membership management flows to invite, add, remove, and assign roles to members.
- Role & permission management scoped per tenant, including default roles and automatic assignment on user creation.

**Billing & Monetization**
- Stripe integration via Laravel Cashier with monthly and yearly plans, trial periods, and plan upgrades/downgrades.
- Usage tracking, plan-based resource limits (`CheckResourceLimit` middleware), and adaptive API rate limiting (`PlanBasedRateLimitMiddleware`).
- Stripe webhook ingestion and notification pipeline for payment events, plus proactive trial ending and payment failure alerts.

**Security & Compliance**
- Authentication flows using Sanctum personal access tokens, optional JWT hand-off for 2FA confirmation, and email verification.
- Two-Factor Authentication with recovery codes and status notifications.
- Security middleware for HTTP headers, IP allow-listing, and suspicious activity checks.
- GDPR tooling to request exports, cancel requests, perform deletions, and deliver data downloads.
- Audit logging middleware that records actor, organization context, and payload changes.

**Developer Experience**
- Modular feature scaffolding through `php artisan make:feature` and auto-loaded routes per module.
- API documentation generated with Dedoc Scramble, available under `/docs/api`.
- Laravel Sail docker-compose stack (PostgreSQL, Redis, and an optional RabbitMQ broker) for local parity with production dependencies.
- Code quality tooling: Pint for formatting, PHPStan for static analysis, Pest-powered automated tests.

## Architecture

- `app/Models` – foundational Eloquent models (users, organizations, roles, permissions, tokens).
- `src/Features/<Module>/<Feature>` – self-contained feature slices that bundle commands, handlers, controllers, requests, and routes.
- `src/Infrastructure` – shared middleware, tenant services, caching helpers, and rate limiting strategies.
- `tests/Feature` & `tests/Unit` – Pest test suites covering authentication, rate limiting, notifications, and more.

`bootstrap/app.php` wires middleware aliases for tenancy, subscription enforcement, plan-based throttling, and security headers. `AppServiceProvider` auto-loads package migrations, configures Cashier models, and augments the generated OpenAPI schema with the required tenant headers.

## Requirements

- PHP 8.4 with extensions: OpenSSL, BCMath, Ctype, Fileinfo, JSON, Mbstring, PDO (pgsql), Tokenizer.
- Composer 2.6 or newer.
- Node.js 20+ and npm (or another compatible package manager).
- PostgreSQL 15+ (other drivers can be used with matching `.env` configuration).
- Redis 7+ for caching and queues (database queues work but are not recommended for production).
- Stripe account with API keys and a webhook signing secret.
- Docker Desktop (optional) to use the bundled Laravel Sail stack (PostgreSQL, Redis, RabbitMQ).

## Getting Started

### Manual installation

1. Clone the repository and install dependencies:
   ```bash
   git clone https://github.com/<your-account>/saas.git
   cd saas
   composer install
   npm install
   ```
2. Bootstrap environment variables:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Update `.env` with database credentials, queue driver, Stripe keys, mail settings, and allowed CORS origins (`CORS_ALLOWED_ORIGINS`).
3. Prepare the database:
   ```bash
   php artisan migrate
   ```
4. Run the application:
   ```bash
   php artisan serve
   npm run dev
   ```
   Alternatively, use `composer dev` to launch the HTTP server, queue listener, log tailing, and Vite in a single process supervisor.
5. Start background workers as needed:
   ```bash
   php artisan queue:work
   php artisan schedule:work
   ```

### Docker with Laravel Sail

1. Install dependencies and copy the environment file:
   ```bash
   composer install
   cp .env.example .env
   ```
2. Adjust `.env` to use the Sail services (`DB_HOST=pgsql`, `REDIS_HOST=redis`, `QUEUE_CONNECTION=database` or `redis`, RabbitMQ credentials, etc.).
3. Boot the containers:
   ```bash
   ./vendor/bin/sail up -d
   ```
4. Run setup commands inside the containers:
   ```bash
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate --seed
   ./vendor/bin/sail npm install
   ./vendor/bin/sail npm run dev
   ```
5. Access the app at `http://localhost` (API) and `http://localhost:5173` (Vite dev server). RabbitMQ management UI is exposed on `http://localhost:15672`.

## Environment & Configuration

Key environment toggles:

- `APP_URL`, `CORS_ALLOWED_ORIGINS` – align API URLs with your frontend.
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` – database settings.
- `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, and `REDIS_*` – cache and queue backends (Redis recommended).
- `RABBITMQ_*` – connection placeholders for the bundled RabbitMQ container; install a compatible queue driver before switching away from database/Redis queues.
- `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` – API credentials; plan-specific IDs map to `STRIPE_PRODUCT_*` and `STRIPE_PRICE_*` variables.
- `JWT_SECRET` – signing key for short-lived JWTs issued during 2FA.

Tenanted API requests must include the `X-Organization-Id` header. When omitted, the platform falls back to the user’s personal context and free-tier limits.

## Developer Workflow

- Scaffold new vertical slices with `php artisan make:feature Module:Action`.
- Reset data during development using `php artisan migrate:fresh --seed`.
- Format code with `./vendor/bin/pint` and run static analysis via `./vendor/bin/phpstan analyse`.
- Stream structured logs with `php artisan pail --timeout=0`.
- Use `composer dev` to run the HTTP server, queue listener, real-time log viewer, and Vite concurrently.

## Background Jobs & Scheduling

- Queue consumers: run `php artisan queue:work` (or `./vendor/bin/sail artisan queue:work`) to process notifications, audits, and other asynchronous jobs.
- Scheduler: add `php artisan schedule:run` to your system cron to dispatch recurring commands such as `subscriptions:trial-ending` and `gdpr:process-deletions`.
- RabbitMQ support: the Sail stack ships a RabbitMQ container, but the application defaults to database/Redis queues until you install a driver (for example, `vyuldashev/laravel-queue-rabbitmq`) and update `QUEUE_CONNECTION`.

## Testing

Run the full suite:

```bash
composer test
```

The test command clears cached configuration and executes Pest feature/unit tests (`php artisan test`). You can target specific tests with:

```bash
./vendor/bin/pest tests/Feature/RateLimitingTest.php
./vendor/bin/pest --filter=subscription
```

Configure a dedicated test database in `.env.testing` for parallel execution (`php artisan test --parallel`).

## API Reference

OpenAPI documentation is generated automatically by Dedoc Scramble. Once the application is running, visit:

```
http://localhost:8000/docs/api
```

Every tenant-aware endpoint requires the `X-Organization-Id` header. Example request:

```bash
curl -X GET http://localhost:8000/api/v1/user \
  -H "Authorization: Bearer <sanctum-token>" \
  -H "X-Organization-Id: <organization-uuid>"
```

Responses include standard rate-limit headers (`X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`) based on the active subscription. Re-generate the spec with `php artisan scramble:generate` before publishing SDKs or public docs.

## Contributing

We welcome issues, discussions, and pull requests:

1. Fork the repository and create a feature branch (`git checkout -b feature/my-improvement`).
2. Follow the setup instructions above and reproduce your change with tests.
3. Run quality gates locally: `./vendor/bin/pint`, `./vendor/bin/phpstan analyse`, and `composer test`.
4. Commit using clear messages and reference related issues.
5. Submit a pull request that explains the motivation and the impact. Screenshots or API examples are highly appreciated.
6. By contributing you agree to uphold a welcoming, inclusive environment similar to the Contributor Covenant.

Feature ideas, bug reports, and security disclosures can also be shared through GitHub Issues. For sensitive topics (security, compliance), please reach out privately before filing a public ticket.

## License

This project is released under the MIT License, allowing commercial and private reuse with attribution. If your clone does not yet contain a `LICENSE` file, you can add the standard MIT template so downstream users have the full legal text.

## Community

- Open an issue to report bugs or request features.
- Share feedback and success stories through discussions or pull requests.
- Star the repository if it helps you build your next SaaS product.
