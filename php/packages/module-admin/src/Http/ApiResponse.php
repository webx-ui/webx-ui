<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http;

use Illuminate\Http\JsonResponse;

/**
 * The shape every WebX UI endpoint answers in.
 *
 * Deliberately thin. Laravel already has the two formats that matter — a paginator serialises
 * with `data` and `meta`, a failed validation returns 422 with `message` and `errors` — and the
 * front end is built against those. This only covers the third case, a plain payload, so it
 * arrives under `data` like everything else instead of at the top level.
 */
final class ApiResponse
{
    public static function data(mixed $payload, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $payload], $status);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }

    public static function noContent(): JsonResponse
    {
        return new JsonResponse(status: 204);
    }
}
