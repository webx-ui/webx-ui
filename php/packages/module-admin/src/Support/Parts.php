<?php

declare(strict_types=1);

namespace WebxUi\Admin\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as Views;
use WebxUi\Blocks\Rendering\Bundles;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Tags\BlockTag;

/**
 * What `@webxPart('recipe-card', ['card' => $card], 'webx-recipes::partials.card')` prints: the
 * component a module declared, when the site has blocks and has customised it; else the theme's
 * view for the point, `parts/recipe-card.blade.php` anywhere in the chain of views (THEMES §10.4:
 * a theme adds to a module's page without a provider and without overriding the page); and the
 * module's own partial otherwise. A point with dots in its name is a directory: `parts.catalog.
 * card.badges` is `parts/catalog/card/badges.blade.php`.
 *
 * Here and not in `module-blocks`, because the one site this has to work on is the site without
 * it. A module calls its partials through this so that it works with blocks and without them,
 * and a directive registered by the package that may be missing would be printed as text on
 * exactly the site that needs the fallback. The tag itself stays `module-blocks`'s: a block's
 * template and a site's layout write `<x-webx-block>`.
 */
final class Parts
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(string $slug, array $data, string $fallback): string
    {
        $fallback = self::view($slug, $fallback);

        if (class_exists(BlockTag::class) && BlockTag::available()) {
            return Container::getInstance()->make(Renderer::class)->tag($slug, $data, [], $fallback)->toHtml();
        }

        return Container::getInstance()->make(Views::class)->make($fallback, $data)->render();
    }

    /**
     * The view a point prints while nobody customised it: the theme's `parts.<point>` when the
     * chain of views has one — the site's own `resources/views` first, then each theme layer —
     * and the module's partial otherwise. «Customise» in the panel starts from the same view.
     */
    public static function view(string $slug, string $fallback): string
    {
        $themed = 'parts.'.$slug;

        return Container::getInstance()->make(Views::class)->exists($themed) ? $themed : $fallback;
    }

    /**
     * What `@webxPartAssets` prints: the styles and script of everything rendered so far — a
     * customised part brings its own — and nothing on a site without blocks. The page's head,
     * printed after its body, like `@webxBlocks`, which a module that may run without blocks
     * cannot write.
     */
    public static function assets(): string
    {
        if (class_exists(Bundles::class)) {
            return Container::getInstance()->make(Bundles::class)->tags()->toHtml();
        }

        return '';
    }
}
