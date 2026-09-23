<?php

declare(strict_types=1);

namespace WebxUi\Menu\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * What a menu refuses to do.
 *
 * Both of these are about a menu a template asks for by name: the panel knows the rule and does
 * not draw the buttons, so reaching one of these means something went at the model directly —
 * an import, an agent, a controller that skipped the check.
 */
class MenuException extends RuntimeException
{
    private function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    /** Answered to the panel the way a refused form is, under the field it is about. */
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

    public static function declaredUndeletable(string $key): self
    {
        return new self((string) __('webx-menu::errors.declared-undeletable', ['key' => $key]));
    }

    public static function declaredUnrenamable(string $key): self
    {
        return new self((string) __('webx-menu::errors.declared-unrenamable', ['key' => $key]), 'key');
    }
}
