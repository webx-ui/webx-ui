<?php

declare(strict_types=1);

namespace WebxUi\Pages\Exceptions;

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
}
