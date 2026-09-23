<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * What a category refuses to do. The message is written to be shown.
 */
final class CategoryException extends RuntimeException
{
    /**
     * Answered to the panel the way a refused form is, so every controller and every agent gets
     * the same 422 without a `try` around each call.
     */
    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        return new JsonResponse(['message' => $this->getMessage(), 'errors' => []], 422);
    }
}
