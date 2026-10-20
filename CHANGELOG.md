# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Security
- Authorization was missing: organization-wide actions are now restricted to admins and owners (`tenant.role` middleware), and deleting an organization to its owner.
- A member of one organization could act on another organization by changing the `{organization}` URL parameter. The parameter must now match `X-Organization-Id`.
- Webhooks and audit logs of other organizations could be read, updated or deleted by id. They are now scoped to the current tenant.
- Organization responses serialized raw user models, which included two-factor secrets and recovery codes. They are now always rendered through `UserResource`.
- Confirming 2FA succeeded with an invalid code. Invalid codes are now rejected.
- The TOTP time window was ignored. Codes are now compared in constant time and cannot be replayed.
- The owner role could be assigned through invitations, member management or role assignment. This is no longer possible.
- Webhook URLs could target internal networks (SSRF). Private and reserved addresses are now refused at creation and at delivery time.
- Added brute-force throttling on login, registration, password reset and the 2FA challenge.
- Disabling 2FA and immediate GDPR deletion now require the current password.
- Destructive GDPR actions no longer use `GET`.
- Audit logs redact sensitive keys at any depth and no longer store query strings.
- Updated dependencies with published security advisories (Laravel framework, Guzzle, league/commonmark, Symfony components, PHPUnit). `firebase/php-jwt` was moved to `^7.0`; the 2FA signing key already satisfies its 256-bit minimum.

### Fixed
- The "request deletion" and "cancel deletion" GDPR endpoints were swapped. The GDPR delete flow called relations that do not exist.
- Updating an organization deactivated it and wiped omitted fields. Updates are now partial.
- `GET /audit-logs` and `GET /audit-logs/events` always failed with a 500 error.
- Webhook deliveries with 4xx/5xx responses were recorded as successful, and failed deliveries were never retried.
- Stripe `customer.subscription.deleted` events were never matched. Cashier's default webhook route shadowed the custom controller.
- Plans are now kept in sync on Stripe price changes. Trials can no longer be reused.
- The `subscriptions:trial-ending` command was scheduled but never registered. Trial "days remaining" was negative.
- Unlimited (`-1`) plans were throttled after the first request. Subscriptions without a plan caused a TypeError.
- Payment-failed notifications queried a column that does not exist and needed a missing `notifications` table.
- `last_login_at` was never persisted.
- Expired invitations blocked new ones. Answered invitations were turned into "expired".
- Global roles were invisible inside a tenant, which broke the owner/admin checks. Tenant "default" roles could leak to other tenants on registration.
- Feature routes skipped the `api` middleware group. Malformed UUIDs caused database errors instead of 404 responses.
- Error responses could use an invalid HTTP status code (`0`). Unbounded `per_page` values were accepted.

### Added
- Logout, password reset, e-mail verification and change-password endpoints.
- Endpoint to regenerate recovery codes.
- Outgoing webhooks for domain events, with signed payloads, wildcard subscriptions, retries and delivery filters.
- Database seeders for the system roles and a default plan catalogue.
- Configuration files for webhooks, GDPR and auditing.
- LICENSE, CONTRIBUTING, SECURITY, Code of Conduct, issue and PR templates, Dependabot configuration.
- Regression tests for tenant isolation, authentication flows, GDPR, webhooks and rate limiting.
