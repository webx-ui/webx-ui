<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\Component;

/**
 * `<x-webx-seo />` — the whole block, for a template that would rather write a tag than a
 * directive. `:for` names the entity the page is about, so an entity source has something to
 * answer about; `:url` overrides the address, which is only useful in a test or a preview.
 */
final class SeoHead extends Component
{
    public function __construct(
        private readonly Seo $seo,
        private readonly ?object $for = null,
        private readonly ?string $url = null,
        private readonly ?string $locale = null,
    ) {}

    public function render(): Htmlable
    {
        return $this->seo->head($this->for, $this->url, $this->locale);
    }
}
