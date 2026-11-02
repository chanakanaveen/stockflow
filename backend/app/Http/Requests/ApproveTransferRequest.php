<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

class ApproveTransferRequest extends PostStockRequest
{
    public function authorize(): bool
    {
        Gate::authorize('approve', $this->route('transfer'));

        return true;
    }
}
