<?php

declare(strict_types=1);

namespace WebxUi\Users\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * The module's scripts, each by its name:
 *
 * - `webx/users/users.js` — of the pages cached for everybody (spec §11.6): it reads the cookie
 *   `webx_visitor` and shows what was printed hidden for the visitor;
 * - `webx/users/sign-in.js` — of the dialog of the sign-in (§12.1): its three views, its openers,
 *   `webx:users:signed-in`. Only on a page the dialog is on.
 *
 * Served as written — a page of plain JavaScript a site installs from Composer has no Node to
 * build it with — and immutable: the address carries a hash of the contents.
 */
final class ScriptController
{
    public const array SCRIPTS = [
        'users' => __DIR__.'/../../resources/js/users.js',
        'sign-in' => __DIR__.'/../../resources/js/sign-in.js',
    ];

    public const string SCRIPT = self::SCRIPTS['users'];

    /** @var array<string, string> */
    private static array $versions = [];

    public function __invoke(): Response
    {
        return self::serve('users');
    }

    public function signIn(): Response
    {
        return self::serve('sign-in');
    }

    public static function contents(string $name = 'users'): string
    {
        return (string) @file_get_contents(self::SCRIPTS[$name]);
    }

    public static function version(string $name = 'users'): string
    {
        return self::$versions[$name] ??= substr(hash('xxh128', self::contents($name)), 0, 16);
    }

    private static function serve(string $name): Response
    {
        return new Response(self::contents($name), 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.self::version($name).'"',
        ]);
    }
}
