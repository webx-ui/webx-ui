<?php

declare(strict_types=1);

namespace WebxUi\Admin\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as Views;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Tags\BlockTag;

/**
 * What `@webxPart('recipe-card', ['card' => $card], 'webx-recipes::partials.card')` prints: the
 * component a module declared, when the site has blocks and has customised it, and the module's
 * own partial otherwise.
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
        if (class_exists(BlockTag::class) && BlockTag::available()) {
            return Container::getInstance()->make(Renderer::class)->tag($slug, $data, [], $fallback)->toHtml();
        }

        return Container::getInstance()->make(Views::class)->make($fallback, $data)->render();
    }
}
