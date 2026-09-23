<?php

declare(strict_types=1);

namespace WebxUi\Blog\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * What the blog refuses to do.
 *
 * Every one of these is a rule the panel knows before it draws the button, so reaching one means
 * something asked the model directly: an import, an agent, a controller that skipped the check.
 * The message is written to be shown, and names the field it belongs under where there is one.
 */
class BlogException extends RuntimeException
{
    private function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    /**
     * Answered to the panel the way a refused form is. Laravel calls this before its handler
     * decides on a status, so every controller and every agent gets the same 422 without a
     * `try` around each call.
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

    /** A rubric with articles in it (§6): deleting it would leave them without a section. */
    public static function rubricHasArticles(int $count): self
    {
        return new self((string) trans('webx-blog::errors.rubric-in-use', ['count' => $count]));
    }

    /** Merging a tag into itself, or into one of the tags being merged away. */
    public static function tagCannotMergeIntoItself(): self
    {
        return new self((string) trans('webx-blog::errors.merge-into-self'), 'keep');
    }
}
