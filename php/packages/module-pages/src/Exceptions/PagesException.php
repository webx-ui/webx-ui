<?php

declare(strict_types=1);

namespace WebxUi\Pages\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * What a page refuses to do.
 *
 * Every one of these is a rule the panel already knows — the capabilities of a node say the
 * same thing before the button is drawn (§4) — so reaching one means something asked the model
 * directly: an import, an agent, a controller that skipped the check. The message is written to
 * be shown, and carries the field it belongs under where there is one.
 */
class PagesException extends RuntimeException
{
    private function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    /**
     * Answered to the panel the way a refused form is, under the field it is about.
     *
     * Laravel calls this before its handler decides on a status, so every controller and every
     * agent gets the same 422 without a `try` around each call — which is what the panel needs
     * anyway: these are rules about the page, not failures of the request.
     */
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

    public static function homeAlreadyExists(): self
    {
        return new self((string) trans('webx-pages::errors.home-exists'), 'parent_id');
    }

    public static function homeCannotBeMoved(): self
    {
        return new self((string) trans('webx-pages::errors.home-immovable'), 'parent_id');
    }

    public static function homeCannotBeDeleted(): self
    {
        return new self((string) trans('webx-pages::errors.home-undeletable'));
    }

    public static function homeHasNoAddress(): self
    {
        return new self((string) trans('webx-pages::errors.home-address'), 'slug');
    }

    /** A page dropped inside its own branch would take the branch with it and leave the tree. */
    public static function pageCannotHoldItself(): self
    {
        return new self((string) trans('webx-pages::errors.move-into-self'), 'target');
    }

    /** Beside the home page is where a second root would be, and there is only one (§2.4). */
    public static function homeHasNoSiblings(): self
    {
        return new self((string) trans('webx-pages::errors.home-no-siblings'), 'zone');
    }

    /**
     * There is no root at all — a database the migration never reached, or one somebody emptied
     * by hand. Said out loud rather than made up for: a second page created as the root would
     * put the site's front page wherever the next request happened to land.
     */
    public static function homeIsMissing(): self
    {
        return new self((string) trans('webx-pages::errors.home-missing'));
    }

    /** A page in the bin is not a place to put a live one; it would go dark with it. */
    public static function parentIsInBin(): self
    {
        return new self((string) trans('webx-pages::errors.parent-trashed'), 'parent_id');
    }
}
