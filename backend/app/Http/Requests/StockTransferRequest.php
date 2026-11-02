<?php

namespace App\Http\Requests;

use App\Models\StockTransfer;
use App\Rules\DecimalQuantity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transfer = $this->route('transfer');
        Gate::authorize($transfer instanceof StockTransfer ? 'update' : 'create', $transfer instanceof StockTransfer ? $transfer : StockTransfer::class);

        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return ['from_store_id' => [$required, 'integer', 'exists:stores,id'], 'to_store_id' => [$required, 'integer', 'exists:stores,id'],
            'lines' => [$required, 'array', 'min:1', 'max:100'], 'lines.*' => ['required', 'array:product_id,quantity'],
            'lines.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'], 'lines.*.quantity' => ['required', new DecimalQuantity]];
    }
}
