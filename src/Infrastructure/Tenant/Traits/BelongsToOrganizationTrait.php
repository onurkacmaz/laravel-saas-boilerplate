<?php

namespace Infrastructure\Tenant\Traits;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Infrastructure\Tenant\Scopes\BelongsToOrganization;
use Infrastructure\Tenant\TenantContext;

trait BelongsToOrganizationTrait
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToOrganizationTrait(): void
    {
        static::addGlobalScope(new BelongsToOrganization);

        static::creating(function ($model) {
            if (! isset($model->organization_id) && TenantContext::hasOrganization()) {
                $model->organization_id = TenantContext::getOrganizationId();
            }
        });
    }

    /**
     * Get the organization that owns the model.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
