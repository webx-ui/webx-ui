<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Server;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * What a handler returned, as the protocol carries it.
 *
 * A module's handler speaks PHP — it returns an array, a string, a model — and knows nothing
 * of `laravel/mcp`. A map becomes structured content with its JSON as the text, so a client
 * that reads structure gets it and one that reads text gets the same; a list or a scalar
 * becomes JSON text; a string is passed through as it is.
 */
final class Results
{
    public static function toResponse(mixed $result): Response|ResponseFactory
    {
        if ($result instanceof Response || $result instanceof ResponseFactory) {
            return $result;
        }

        if ($result === null) {
            return Response::text('Done.');
        }

        if (is_string($result)) {
            return Response::text($result);
        }

        if ($result instanceof Arrayable) {
            $result = $result->toArray();
        } elseif ($result instanceof JsonSerializable) {
            $result = $result->jsonSerialize();
        }

        if (is_array($result) && $result !== [] && ! array_is_list($result)) {
            return Response::structured($result);
        }

        return Response::json($result);
    }

    /** The same for a resource, which is text by definition: pretty JSON for anything but a string. */
    public static function toText(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }

        if ($result instanceof Arrayable) {
            $result = $result->toArray();
        }

        return (string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
