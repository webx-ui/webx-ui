<?php

declare(strict_types=1);

namespace WebxUi\Seo\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;
use WebxUi\Seo\Features;
use WebxUi\Seo\Links\LinkBlocks;

/**
 * `<x-webx-seo::links />` — the interlinking block of the page being answered (§18.4).
 *
 * Nothing at all when the feature is off, when the page has no block, or when every link in it
 * is broken — so a template can carry the tag on every page whether or not the project uses it.
 * The markup is the `webx-seo::links` view, which a site publishes and restyles.
 */
final class Links extends Component
{
    public function __construct(
        private readonly LinkBlocks $blocks,
        private readonly Request $request,
    ) {}

    public function render(): View|string
    {
        if (! Features::links()) {
            return '';
        }

        $block = $this->blocks->forRequest($this->request);

        return $block === null ? '' : view('webx-seo::links', [
            'heading' => $block['heading'],
            'links' => $block['links'],
            'id' => 'webx-links-'.substr(md5(serialize($block['links'])), 0, 8),
        ]);
    }
}
