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
        if (TenantContext::hasOrganization()) {
            $builder->where($model->getTable().'.organization_id', TenantContext::getOrganizationId());
        }
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
