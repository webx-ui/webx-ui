<?php

declare(strict_types=1);

namespace WebxUi\Routing\Exceptions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * An address the registry will not store, raised as a validation error on the slug field.
 *
 * A validation exception rather than a plain one because of where this happens: an editor is
 * saving a form, and the answer they need is "this address is taken", under the field they can
 * change — not a 500. The message is English, like every other default a library ships (see
 * CLAUDE.md §4): whoever opens the form is the one who translates it, by catching this and
 * re-raising with their own message, or by translating the key in their own request class.
 */
class PathRejected extends ValidationException
{
    public string $path = '';

    public string $attribute = 'slug';

    public static function taken(string $path, string $attribute = 'slug', ?string $by = null): self
    {
        return self::make($attribute, $path, $by === null
            ? sprintf('The address "/%s" is already taken.', $path)
            : sprintf('The address "/%s" is already taken by "%s".', $path, $by));
    }

    /**
     * The address belongs to the application itself — a route of the project, the panel, or a
     * directory the web server answers from. Losing that race quietly is worse than this (§10).
     */
    public static function reserved(string $path, string $attribute = 'slug'): self
    {
        return self::make($attribute, $path, sprintf('The address "/%s" is reserved by the application.', $path));
    }

    public static function tooLong(string $path, int $limit, string $attribute = 'slug'): self
    {
        return self::make($attribute, $path, sprintf('The address is %d characters long; the limit is %d.', mb_strlen($path), $limit));
    }

    private static function make(string $attribute, string $path, string $message): self
    {
        $validator = Validator::make([], []);
        $validator->errors()->add($attribute, $message);

        $exception = new self($validator);
        $exception->path = $path;
        $exception->attribute = $attribute;

        return $exception;
    }
}
