<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('category');

        return $model instanceof Category ? Gate::allows('update', $model) : Gate::allows('create', Category::class);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['name' => [$this->isMethod('PATCH') ? 'sometimes' : 'required', 'string', 'max:255'], 'is_active' => ['sometimes', 'boolean']];
    }
}
