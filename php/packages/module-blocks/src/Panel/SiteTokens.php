<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Container\Container;
use Throwable;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\Tokens;
use WebxUi\Themes\Vocabulary;

/**
 * The vocabulary of the site's theme chain, when the site has one (THEMES §8) — what the token
 * lints of a block type are said against. `webx-ui/themes` is not a dependency of this package:
 * a site without it, or with no theme configured, writes its blocks in whatever colours it likes,
 * and is not told otherwise.
 */
final class SiteTokens
{
    public static function vocabulary(): ?Vocabulary
    {
        if (! class_exists(ThemeChain::class)) {
            return null;
        }

        $container = Container::getInstance();

        try {
            if (! $container->bound(ThemeChain::class) || $container->make(ThemeChain::class)->isEmpty()) {
                return null;
            }

            return $container->make(Tokens::class)->vocabulary();
        } catch (Throwable) {
            // A chain that does not resolve is webx:theme:check's to say, not a lint's.
            return null;
        }
    }

    /**
     * The names, without `--site-`, for the editor's live lints; null without a theme.
     *
     * @return list<string>|null
     */
    public static function names(): ?array
    {
        return self::vocabulary()?->names();
    }

    /**
     * Of those, the optional ones (THEMES §7.2): a block reads them with a fallback.
     *
     * @return list<string>|null
     */
    public static function optional(): ?array
    {
        $vocabulary = self::vocabulary();

        return $vocabulary === null ? null : array_values(array_filter($vocabulary->names(), $vocabulary->optional(...)));
    }
}
