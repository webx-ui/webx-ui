<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as Views;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Blocks\Rendering\Bundles;

/**
 * The page of the site a block type is drawn on in its editor: the site's layout — header,
 * footer, fonts, the ground colour — with an empty pair of markers where the content goes.
 *
 * The block itself comes from `RenderController`, which the editor calls on every change and
 * swaps in between the markers. One page and many swaps rather than one page per change: the
 * site around the block does not change while the block is being typed.
 */
final class StageController
{
    /** The key the block is drawn under — the one `RenderController` uses when none is asked. */
    public const KEY = 'sample';

    /** The `<style>` the panel writes the block's styles into. */
    public const STYLES = 'wx-stage-styles';

    public function __invoke(Views $views, Config $config, Bundles $bundles): Response
    {
        $layout = $config->get('webx-blocks.layout');

        $response = response($views->make('webx-blocks::stage', [
            'layout' => is_string($layout) && $layout !== '' ? $layout : 'webx-blocks::standalone',
            'runtime' => $bundles->runtimeUrl(),
            'key' => self::KEY,
            'styles' => self::STYLES,
        ])->render());

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
