<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ResponseTrait
{
    private function successResponse(string $message, ?string $redirect = null, array $additional_data = []): JsonResponse
    {
        $response_data = [
            'status' => 1,
            'type' => 'toast',
            'message' => $message,
        ];
        if ($redirect) {
            $response_data['redirect'] = $redirect;
        } else {
            $response_data['refresh'] = 'true';
        }

        if ($additional_data !== []) {
            $response_data = array_merge($response_data, $additional_data);
        }

        return response()->json($response_data);
    }

    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'status' => 2,
            'type' => 'toast',
            'message' => $message,
            'refresh' => 'true',
        ]);
    }
}
