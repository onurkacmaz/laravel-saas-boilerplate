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
- Stripe `customer.subscription.updated` events for `incomplete_expired` subscriptions crashed with a TypeError, so Stripe kept retrying them.
- Payment-succeeded/failed and subscription-upgraded notification listeners were never registered. `SubscriptionUpgraded` was never dispatched.
- Subscription payments were notified twice (once for the `invoice.*` event and once for the `payment_intent.*` event).
- Accepting an expired invitation did not persist its "expired" status: the update was rolled back with the transaction.
- A stale `Authorization` header turned the 2FA challenge into a 302 redirect.
- 2FA challenge tokens are now validated against the application clock.
- Webhook and audit event searches are case-insensitive on every database.
- Placeholder `broadcastOn()` methods were removed from events that are not broadcastable.
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
- Tests for previously uncovered code, mainly billing: Stripe webhooks, checkout, cancellation, limits, usage, notifications and the trial-ending command.
- End-to-end flow tests:
  - onboarding and invitations,
  - the 2FA lifecycle,
  - password recovery,
  - the billing lifecycle,
  - GDPR erasure,
  - webhook delivery.
- Coverage now measures `app/` too and excludes test files. CI enforces a minimum of 90%; local line coverage is 98.5%.
- Permissions are now enforced: `tenant.permission:<slug>` middleware and `Organization::userHasPermission()`. Until now, permissions attached to roles had no effect.
- `UsageTracker::registerCounter()` lets applications count their own plan-limited resources. Until now, `limit:<resource>` never blocked anything but `users` and `api_calls`, and the usage summary reported 0 for other resources.
- Applications can add their own sections to the GDPR export (`ExportsUserData` contributors tagged `UserDataExporter::CONTRIBUTORS`).
- GDPR erasure now raises `OrganizationDeleted` for the organizations it deletes, like `DELETE /organizations/{id}`. Listeners never saw those deletions before.
- `plan.feature:<feature>` middleware, which requires a feature of the organization's plan.
- The organization feature flags are listed in `config/settings.php`, so applications can add their own. They were hard-coded to `advanced_analytics`, `api_access` and `custom_branding`.
- `AuditLogger::log()` and `action()` accept an `organizationId`, to audit events outside a tenant request (queued jobs, scheduled commands). They could only use the current tenant.
- `CacheService::forget()` removes a value stored with `remember()`. Only the fixed `clear*()` methods existed.
- The thresholds of `security.check` are configurable (`config/security.php`), and a route can allow more requests per minute (`security.check:600`) or disable the rate check (`security.check:0`). The defaults keep the previous values, except that the request rate now trips above 100 requests per minute as documented, instead of above 101.
- `ResourceLimits::registerDefault()` sets the limit of a resource whose plan does not define one. Such resources were unlimited.
- `PlanSeeder::extend()` adds limits and features to the seeded plans, and `DatabaseSeeder` runs the seeders tagged `DatabaseSeeder::MODULE_SEEDERS`, so modules no longer edit the core seeders.
- `GET /api/v1/webhooks/events` lists the events webhooks can subscribe to (`config('webhooks.events')`).
- `TenantContext::runAs()` and `runWithoutTenant()` run code for another tenant, or none, and restore the previous one.
- The SSRF guard moved to `Infrastructure\Security` (`UrlGuard`, `SafeUrl` rule) so any feature requesting user-supplied URLs can use it, with a neutral setting: `SECURITY_ALLOW_PRIVATE_URLS`. `WebhookUrlGuard`, `SafeWebhookUrl` and `WEBHOOKS_ALLOW_PRIVATE_URLS` still work and are deprecated.
- `IpWhitelist::allows()` and the `IpOrCidr` rule match and validate IP allow-lists stored with application data. `ip.whitelist` only takes lists written in the route.
- The README explains how to add a module and lists the core's extension points; CONTRIBUTING notes that Scramble resolves form request rules without a tenant.
- `OrganizationSetting::factory()` creates the production default settings instead of random preferences and flags (random ones with `randomized()`), and `userInOrganization()` uses the system roles for "owner", "admin" and "member" like production. Tests depending on random settings were flaky.
- Core tests no longer assume that no module registers limits or plan extensions; they failed in applications that do.
