<?php

namespace App\Http\Requests;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('store');

        return $model instanceof Store ? Gate::allows('update', $model) : Gate::allows('create', Store::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $store = $this->route('store');

        return ['code' => [$required, 'string', 'max:32', 'regex:/\\A[A-Z0-9][A-Z0-9._-]*\\z/', Rule::unique('stores', 'code')->ignore($store instanceof Store ? $store->id : null)],
            'name' => [$required, 'string', 'max:255'], 'location_label' => ['sometimes', 'nullable', 'string', 'max:255'], 'is_active' => ['sometimes', 'boolean']];
    }
}
