<?php

namespace Features\Audit\Infrastructure\Support;

use Features\Audit\Infrastructure\Eloquent\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Infrastructure\Tenant\Middleware\SetTenantContext;
use Infrastructure\Tenant\TenantContext;

readonly class AuditLogger
{
    public function __construct(
        private TenantContext $tenantContext
    ) {}

    /**
     * Log an audit event.
     */
    public function log(
        string $event,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?array $metadata = null,
        ?Request $request = null
    ): AuditLog {
        $request = $request ?? request();

        // The tenant context is cleared once the tenant middleware finished, so
        // fall back to the organization it recorded on the request.
        $organizationId = $this->tenantContext->getOrganizationId()
            ?? $request->attributes->get(SetTenantContext::REQUEST_ATTRIBUTE);

        return AuditLog::query()->create([
            'organization_id' => $organizationId,
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $this->filterSensitiveData($oldValues),
            'new_values' => $this->filterSensitiveData($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log a model creation event.
     */
    public function created(Model $model, ?array $metadata = null): AuditLog
    {
        return $this->log(
            event: 'created',
            auditable: $model,
            newValues: $model->getAttributes(),
            metadata: $metadata
        );
    }

    /**
     * Log a model update event.
     */
    public function updated(Model $model, ?array $metadata = null): AuditLog
    {
        return $this->log(
            event: 'updated',
            auditable: $model,
            oldValues: $model->getOriginal(),
            newValues: $model->getAttributes(),
            metadata: $metadata
        );
    }

    /**
     * Log a model deletion event.
     */
    public function deleted(Model $model, ?array $metadata = null): AuditLog
    {
        return $this->log(
            event: 'deleted',
            auditable: $model,
            oldValues: $model->getAttributes(),
            metadata: $metadata
        );
    }

    /**
     * Log a custom action event.
     */
    public function action(string $action, ?Model $model = null, ?array $metadata = null): AuditLog
    {
        return $this->log(
            event: $action,
            auditable: $model,
            metadata: $metadata
        );
    }

    /**
     * Log an authentication event.
     */
    public function auth(string $event, ?array $metadata = null): AuditLog
    {
        return $this->log(
            event: "auth.$event",
            metadata: $metadata
        );
    }

    /**
     * Filter sensitive data from audit logs.
     */
    private function filterSensitiveData(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'secret',
            'api_key',
            'api_secret',
            'credit_card',
            'cvv',
            'ssn',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'remember_token',
        ];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->filterSensitiveData($value);
            }
        }

        return $data;
    }

    public function query(): Builder
    {
        $query = AuditLog::query()->with(['user', 'organization']);

        $organizationId = $this->tenantContext->getOrganizationId();

        // Without a tenant the query must return nothing rather than every
        // organization's logs.
        $organizationId
            ? $query->where('organization_id', $organizationId)
            : $query->whereRaw('1 = 0');

        return $query->latest();
    }

    /**
     * Get audit logs for a specific model.
     */
    public function forModel(Model $model): Builder
    {
        return AuditLog::query()
            ->where('auditable_type', get_class($model))
            ->where('auditable_id', $model->getKey())
            ->with(['user', 'organization'])
            ->latest();
    }

    /**
     * Get audit logs for a specific user.
     */
    public function forUser(string $userId): Builder
    {
        return AuditLog::query()
            ->where('user_id', $userId)
            ->with(['auditable', 'organization'])
            ->latest();
    }

    /**
     * Get audit logs for a specific event.
     */
    public function forEvent(string $event): Builder
    {
        return $this->query()->where('event', $event);
    }
}
