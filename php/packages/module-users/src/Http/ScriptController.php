<?php

declare(strict_types=1);

namespace WebxUi\Users\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * `webx/users/users.js` — the script of the pages cached for everybody (spec §11.6): it reads
 * the cookie `webx_visitor` and shows what was printed hidden for the visitor.
 *
 * Served as written — a page of plain JavaScript a site installs from Composer has no Node to
 * build it with — and immutable: the address carries a hash of the contents.
 */
final class ScriptController
{
    public const string SCRIPT = __DIR__.'/../../resources/js/users.js';

    private static ?string $version = null;

    public function __invoke(): Response
    {
        return new Response(self::contents(), 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.self::version().'"',
        ]);
    }

    public static function contents(): string
    {
        return (string) @file_get_contents(self::SCRIPT);
    }

    public static function version(): string
    {
        return self::$version ??= substr(hash('xxh128', self::contents()), 0, 16);
    }
}
