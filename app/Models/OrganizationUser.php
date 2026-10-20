<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $organization_id
 * @property string $user_id
 * @property string $role_id
 */
class OrganizationUser extends Pivot
{
    protected $table = 'organization_user';

    protected $fillable = [
        'organization_id',
        'user_id',
        'role_id',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class)->withoutGlobalScopes();
    }
}
