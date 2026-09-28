<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use WebxUi\Banners\Models\Place;
use WebxUi\Banners\Places;
use WebxUi\Banners\Rendering\BannerQuery;

if (! function_exists('banners')) {
    /**
     * `banners('hero')` — the banners of a place a template may show, as cards (see
     * {@see BannerQuery}). Without a place, every banner of the site a reader may see.
     *
     *     banners('hero')->get();
     *     banners(['hero', 'promo'])->take(3);
     *     banners()->only([5, 2]);
     *
     * Guarded, because a site may have taken the name first — and a package that redeclares a
     * function of the application is a fatal error at boot rather than a conflict somebody can
     * work around. `php artisan webx:doctor` says when it is not ours.
     *
     * @param  string|int|Place|iterable<string|int|Place>|null  $places
     */
    function banners(string|int|Place|iterable|null $places = null): BannerQuery
    {
        return (new BannerQuery)->in($places);
    }
}

if (! function_exists('banners_layout')) {
    /**
     * `banners_layout('hero')` — how the site's template lays the place out: `layout` (single,
     * random or slider) and the options of `webx-banners.options`, merged package ← config ←
     * place ← `$layout`. The package draws nothing with them; the template does.
     *
     * A place asked for by id is looked up by its key; a place nobody knows gets the defaults.
     *
     * @return array<string, mixed>
     */
    function banners_layout(string|int|null $place = null, ?string $layout = null): array
    {
        if (is_int($place)) {
            $key = Place::query()->whereKey($place)->value('key');
            $place = is_string($key) ? $key : null;
        }

        return Container::getInstance()->make(Places::class)->options($place, $layout);
    }
}
