<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Infrastructure\Tenant\Traits\BelongsToOrganizationTrait;

/**
 * @property string $id
 * @property string|null $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Permission extends Model
{
    use BelongsToOrganizationTrait, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'organization_id',
    ];

    /**
     * System-wide records (NULL organization) are visible to every tenant.
     */
    public function includesSystemRecords(): bool
    {
        return true;
    }

    public function isSystem(): bool
    {
        return $this->organization_id === null;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_roles');
    }
}
