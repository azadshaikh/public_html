<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;

trait ApiResponds
{
    protected function success(mixed $data, string $type, array $meta = [], int $status = 200): JsonResponse
    {
        $baseMeta = [
            'type' => $type,
            'generated_at' => now()->toISOString(),
            'api_version' => 'v1',
        ];

        return response()->json([
            'data' => $data,
            'meta' => array_merge($baseMeta, $meta),
        ], $status);
    }

    protected function error(string $error, string $message, int $status = 500, array $meta = []): JsonResponse
    {
        $payload = [
            'error' => $error,
            'message' => $message,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }
}
