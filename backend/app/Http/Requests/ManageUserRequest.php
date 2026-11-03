<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ManageUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('viewAny', User::class);

        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $user = $this->route('user');

        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user instanceof User ? $user->id : null)],
            'password' => [$user instanceof User ? 'sometimes' : 'required', 'string', 'min:12', 'max:128'],
            'role' => ['required', Rule::in(['System Admin', 'Inventory Manager', 'Store Keeper', 'Auditor'])],
            'store_ids' => ['present', 'array'], 'store_ids.*' => ['integer', 'distinct', 'exists:stores,id'], 'is_active' => ['required', 'boolean']];
    }
}
