<?php

declare(strict_types=1);

namespace WebxUi\Seo\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Localization\Locales;
use WebxUi\Seo\Rendering\Breadcrumbs as Trail;
use WebxUi\Seo\Rendering\Seo;

/**
 * `<x-webx-seo::breadcrumbs :for="$article" />` — the crumbs a reader sees (§17.4).
 *
 * The list is the one the `BreadcrumbList` is printed from, so the view decides how the trail
 * looks and nothing about what is in it. Without `:for` it takes the entity the address registry
 * found for the request, the way the `<head>` does. Nothing at all when there is no trail.
 */
final class Breadcrumbs extends Component
{
    public function __construct(
        private readonly Trail $trail,
        private readonly Seo $seo,
        private readonly Locales $locales,
        private readonly ?object $for = null,
        private readonly ?string $locale = null,
    ) {}

    public function render(): View|string
    {
        $locale = $this->locale ?? $this->locales->current();
        $crumbs = $this->trail->trail($this->for ?? $this->seo->subject(), $locale);

        return $crumbs === [] ? '' : view('webx-seo::breadcrumbs', ['crumbs' => $crumbs, 'locale' => $locale]);
    }
}
