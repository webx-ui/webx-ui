<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * What the module refuses to do.
 *
 * Every one of these is a rule the panel already knows and already draws — a form with
 * submissions has no delete button — so reaching one means something asked the model directly:
 * an import, an agent, a controller written later. The message is meant to be read, and it
 * answers as a refused form does, so no caller needs a `try` around it.
 */
abstract class InboxException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        return new JsonResponse([
            'message' => $this->getMessage(),
            'errors' => $this->field === null ? [] : [$this->field => [$this->getMessage()]],
        ], 422);
    }
}
