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
 * change — not a 500. The words come from `webx-routing::paths` in the language of the request:
 * the panel shows this message as it is, and an English sentence in a Russian form read as a
 * fault. A site that wants other words overrides the keys, or catches this and re-raises.
 */
class PathRejected extends ValidationException
{
    public string $path = '';

    public string $attribute = 'slug';

    public static function taken(string $path, string $attribute = 'slug', ?string $by = null): self
    {
        return self::make($attribute, $path, $by === null
            ? self::say('taken', ['path' => '/'.$path])
            : self::say('taken-by', ['path' => '/'.$path, 'by' => $by]));
    }

    /**
     * The address belongs to the application itself — a route of the project, the panel, or a
     * directory the web server answers from. Losing that race quietly is worse than this (§10).
     */
    public static function reserved(string $path, string $attribute = 'slug'): self
    {
        return self::make($attribute, $path, self::say('reserved', ['path' => '/'.$path]));
    }

    public static function tooLong(string $path, int $limit, string $attribute = 'slug'): self
    {
        return self::make($attribute, $path, self::say('too-long', ['length' => mb_strlen($path), 'limit' => $limit]));
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private static function say(string $key, array $replace): string
    {
        return (string) trans('webx-routing::paths.'.$key, $replace);
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
