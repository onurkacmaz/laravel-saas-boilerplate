<?php

namespace Infrastructure\Tenant\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Infrastructure\Tenant\TenantContext;

class BelongsToOrganization implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! TenantContext::hasOrganization()) {
            return;
        }

        $column = $model->getTable().'.organization_id';

        // Some models (e.g. roles and permissions) have system-wide records that
        // are shared by every tenant; those are stored with a NULL organization.
        if (method_exists($model, 'includesSystemRecords') && $model->includesSystemRecords()) {
            $builder->where(function (Builder $query) use ($column) {
                $query->where($column, TenantContext::getOrganizationId())
                    ->orWhereNull($column);
            });

            return;
        }

        $builder->where($column, TenantContext::getOrganizationId());
    }

    /**
     * Extend the query builder with methods to bypass the scope.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutOrganization', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('forOrganization', function (Builder $builder, string $organizationId) {
            return $builder->withoutGlobalScope($this)
                ->where($builder->getModel()->getTable().'.organization_id', $organizationId);
        });

        $builder->macro('forAllOrganizations', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
