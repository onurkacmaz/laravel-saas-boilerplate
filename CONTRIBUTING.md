# Contributing

Thanks for taking the time to contribute! Bug reports, feature ideas,
documentation improvements and pull requests are all welcome.

## Ground rules

- Be respectful: this project follows the [Code of Conduct](CODE_OF_CONDUCT.md).
- Report security issues privately, see [SECURITY.md](SECURITY.md).
- Open an issue to discuss larger changes before investing a lot of time.

## Local setup

```bash
git clone https://github.com/onurkacmaz/laravel-saas-boilerplate.git
cd laravel-saas-boilerplate
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

The test suite runs on an in-memory SQLite database and needs no external service:

```bash
composer test
```

## Project structure

Every capability is a vertical slice under `src/Features/<Module>/<Feature>`:

```
src/Features/Billing/CreateInvoice/
├── Application/        # Command (DTO), Handler, Controller, FormRequest
├── Events/             # Domain events raised by the feature (optional)
├── Providers/          # Service provider: routes, listeners, commands
├── Routes/api.php      # The feature's routes
└── Tests/Feature/      # Pest tests for the feature
```

Scaffold a new slice with `php artisan make:feature Billing:CreateInvoice`, then
register the generated service provider in `bootstrap/providers.php`.

Guidelines:

- Tenant-aware routes use `auth:sanctum`, `tenant.context` and `tenant.required`;
  add `tenant.role:admin` (or `tenant.role:owner`) to anything that changes
  organization-wide state.
- Never trust identifiers coming from the URL or the body: always query through
  the current organization (`TenantContext::getOrganization()->webhooks()->findOrFail($id)`).
- Return API resources instead of raw models so sensitive attributes never leak.
- Keep handlers free of HTTP concerns; controllers translate requests into commands.
- Scramble builds the API documentation by calling every `FormRequest::rules()`
  without a request, so without a tenant. A rule that reads the database must
  cope with `TenantContext::getOrganization()` being `null`: a failing query
  there aborts the surrounding transaction on PostgreSQL and breaks the tests.
- Tests get the system roles from `userInOrganization('owner'|'admin'|'member')`
  and the production defaults from `OrganizationSetting::factory()`; use its
  `randomized()` state only when a test is about arbitrary settings.

## Pull request checklist

1. Add or update tests for your change (`composer test` must pass).
2. Format the code: `composer format` (CI runs `pint --test`).
3. Update the README / CHANGELOG when behavior or configuration changes.
4. Write a clear description: the problem, the solution and how you tested it.
