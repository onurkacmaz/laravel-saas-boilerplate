# Security Policy

## Supported versions

Security fixes are applied to the `main` branch. Please make sure you are running
the latest version before reporting an issue.

## Reporting a vulnerability

**Please do not open a public GitHub issue for security vulnerabilities.**

Use GitHub's [private vulnerability reporting](https://github.com/onurkacmaz/laravel-saas-boilerplate/security/advisories/new)
to send us the details. Include:

- a description of the issue and its impact,
- the steps (or a proof of concept) to reproduce it,
- the affected endpoints, files or versions.

You can expect an acknowledgement within a few days. We will keep you informed
while the fix is prepared and credit you in the release notes unless you prefer
to stay anonymous.

## Hardening checklist for deployments

- Set a strong `APP_KEY` (and optionally `JWT_SECRET`) and keep `APP_DEBUG=false` in production.
- Configure `STRIPE_WEBHOOK_SECRET` so Stripe webhook signatures are verified.
- Keep `WEBHOOKS_ALLOW_PRIVATE_URLS=false` outside of local development.
- Restrict `CORS_ALLOWED_ORIGINS` to your own frontends.
- Run the queue worker and the scheduler (`php artisan schedule:run` every minute).
- Serve the application over HTTPS only.
