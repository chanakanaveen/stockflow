<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Models\StockDocument;
use App\Rules\DecimalQuantity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StockDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');
        if ($document instanceof StockDocument) {
            Gate::authorize('update', $document);
        } else {
            Gate::authorize('create', [StockDocument::class, DocumentType::from($this->route('kind'))]);
        }

        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'store_id' => [$required, 'integer', 'exists:stores,id'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lines' => [$required, 'array', 'min:1', 'max:100'],
            'lines.*' => ['required', 'array:product_id,quantity'],
            'lines.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'lines.*.quantity' => ['required', new DecimalQuantity],
        ];
    }
}
