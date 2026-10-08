<?php

declare(strict_types=1);

namespace WebxUi\Admin\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Stringable;
use WebxUi\Admin\Shortcodes\Shortcode;
use WebxUi\Admin\Shortcodes\Shortcodes as Registry;
use WebxUi\Admin\Shortcodes\ShortcodeText;

/**
 * @method static void register(string $name, Closure|string $html, Closure|string|null $plain = null, ?string $description = null)
 * @method static void source(Closure $source)
 * @method static array<string, Shortcode> all()
 * @method static bool has(string $name)
 * @method static Shortcode|null get(string $name)
 * @method static list<array{name: string, description: string|null, html: string, plain: string, origin: string}> list()
 * @method static string html(string|Stringable|null $text)
 * @method static string htmlIn(?string $html)
 * @method static string plain(string|Stringable|null $text)
 * @method static string text(string|Stringable|null $html)
 * @method static string|ShortcodeText|null resolve(?string $text)
 *
 * @see Registry
 */
final class Shortcodes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Registry::class;
    }
}
