<?php

declare(strict_types=1);

use WebxUi\Reviews\Rendering\ReviewQuery;

if (! function_exists('reviews')) {
    /**
     * `reviews()` — the reviews a template may show, as cards (see {@see ReviewQuery}).
     *
     *     reviews()->in($categories)->take(6);
     *     reviews()->only([12, 7]);
     *     reviews()->except($review);
     *     reviews()->categories();
     *
     * Guarded, because the name is short enough that a site may have taken it first — and a
     * package that redeclares a function of the application is a fatal error at boot rather than
     * a conflict somebody can work around. `php artisan webx:doctor` says when it is not ours.
     */
    function reviews(): ReviewQuery
    {
        return new ReviewQuery;
    }
}
