<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class InventoryException extends RuntimeException
{
    /** @param list<array<string, mixed>> $details */
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 409, public readonly array $details = [])
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['error' => ['code' => $this->errorCode, 'message' => $this->getMessage(), 'details' => $this->details], 'meta' => ['request_id' => request()->attributes->get('request_id')]], $this->status);
    }
}
