<?php

namespace App\Http\Requests\IdentityProvider;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIdentityProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('identity_provider')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('identity_providers', 'slug')->ignore($id)],
            'enabled' => ['boolean'],
            'sort_order' => ['integer'],
            'fields' => ['sometimes', 'array'],
            'claim_mappings' => ['nullable', 'array'],
            'role_mapping' => ['nullable', 'array'],
            'role_mapping.*.claim' => ['required_with:role_mapping', 'string'],
            'role_mapping.*.operator' => ['required_with:role_mapping', Rule::in(['equals', 'contains', 'one_of'])],
            'role_mapping.*.value' => ['present'],
            'role_mapping.*.role' => ['required_with:role_mapping', Rule::in(UserRole::values())],
            'default_role' => ['nullable', Rule::in(config('sso.assignable_default_roles'))],
            'allow_jit' => ['boolean'],
            'sync_role_on_login' => ['boolean'],
            'link_by_email' => ['boolean'],
        ];
    }
}
