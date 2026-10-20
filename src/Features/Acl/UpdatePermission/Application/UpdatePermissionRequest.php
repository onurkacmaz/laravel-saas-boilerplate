<?php

namespace Features\Acl\UpdatePermission\Application;

use Features\Acl\Shared\Support\AclRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $permissionId = $this->route('permission');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.:-]+$/', AclRules::uniqueSlug('permissions', is_string($permissionId) ? $permissionId : null)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
