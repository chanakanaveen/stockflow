<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiError
{
    public static function fromResponse(Response $response): Response
    {
        if (! request()->is('api/*')) {
            return $response;
        }
        $status = $response->getStatusCode();
        [$code, $message] = match ($status) {
            401 => ['UNAUTHENTICATED', 'Please sign in to continue.'],
            403 => ['FORBIDDEN', 'You do not have permission to perform this action.'],
            404 => ['NOT_FOUND', 'The requested resource was not found.'],
            405 => ['METHOD_NOT_ALLOWED', 'This method is not allowed.'],
            419 => ['SESSION_EXPIRED', 'Your session expired. Please sign in again.'],
            422 => ['VALIDATION_ERROR', 'Please check the submitted information.'],
            429 => ['RATE_LIMITED', 'Too many attempts. Please try again later.'],
            default => ['SERVER_ERROR', 'An unexpected error occurred.'],
        };
        $details = [];
        if ($status === 422 && $response instanceof JsonResponse) {
            foreach (($response->getData(true)['errors'] ?? []) as $field => $messages) {
                foreach ($messages as $validationMessage) {
                    $details[] = ['field' => $field, 'message' => $validationMessage];
                }
            }
        }
        $result = response()->json([
            'error' => ['code' => $code, 'message' => $message, 'details' => $details],
            'meta' => ['request_id' => request()->attributes->get('request_id')],
        ], $status);
        foreach (['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Allow'] as $header) {
            if ($response->headers->has($header)) {
                $result->headers->set($header, $response->headers->get($header));
            }
        }

        return $result;
    }
}
