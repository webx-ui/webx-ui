<?php

declare(strict_types=1);

namespace WebxUi\Users\Rendering;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory;
use InvalidArgumentException;

/**
 * Which Blade component a page of the module stands in (spec §11.4).
 *
 * The sign-in and the registration — the site's layout (`webx-users.layout`, `'layout'` once
 * `webx:panel --sync` has seen one) with the handle `users.auth`, or the module's bare document.
 * The account — `layout.account`, the composition of the theme that puts the menu beside the
 * content, wherever the site has both; otherwise the same as the sign-in, so a site without the
 * theme still gets whole pages.
 */
final class Layouts
{
    public const string STANDALONE = 'webx-users::standalone';

    public static function auth(): string
    {
        $layout = config('webx-users.layout');

        return is_string($layout) && $layout !== '' ? $layout : self::STANDALONE;
    }

    public static function account(): string
    {
        $own = config('webx-users.account_layout');

        if (is_string($own) && $own !== '') {
            return $own;
        }

        return self::auth() === 'layout' && self::viewExists('components.layout.account')
            ? 'layout.account'
            : self::auth();
    }

    /** The theme's views join the finder at run time, out of sight of a static look at the disk. */
    private static function viewExists(string $view): bool
    {
        try {
            Container::getInstance()->make(Factory::class)->getFinder()->find($view);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
