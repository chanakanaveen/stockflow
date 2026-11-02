<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PostStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('post', $this->route('document'));

        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [];
    }

    public function idempotencyKey(): string
    {
        $key = $this->header('Idempotency-Key');
        if (! is_string($key) || ! preg_match('/\\A[\\x21-\\x7e][\\x20-\\x7e]{0,127}\\z/D', $key)) {
            throw ValidationException::withMessages(['Idempotency-Key' => ['A nonblank printable ASCII Idempotency-Key of at most 128 characters is required.']]);
        }

        return $key;
    }
}
