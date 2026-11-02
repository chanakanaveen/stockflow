<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Rules\DecimalQuantity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('product');

        return $model instanceof Product ? Gate::allows('update', $model) : Gate::allows('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => strtoupper(trim($this->input('sku')))]);
        }
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $product = $this->route('product');

        return ['sku' => [$required, 'string', 'max:64', 'regex:/\\A[A-Z0-9][A-Z0-9._-]*\\z/', Rule::unique('products', 'sku')->ignore($product instanceof Product ? $product->id : null)],
            'name' => [$required, 'string', 'max:255'], 'category_id' => [$required, 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'base_uom' => [$required, 'string', 'max:32'], 'reorder_level' => ['sometimes', new DecimalQuantity(true)], 'is_active' => ['sometimes', 'boolean']];
    }
}
