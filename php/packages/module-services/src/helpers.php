<?php

declare(strict_types=1);

use WebxUi\Services\Rendering\ServiceQuery;

if (! function_exists('services')) {
    /**
     * `services()` — the services a template may show, as cards (see {@see ServiceQuery}).
     *
     *     services()->in('implants')->take(6);
     *     services()->only([12, 7, 30]);
     *     services()->except($service)->take(3);
     *     services()->categories();
     *
     * Guarded, because the name is short enough that a site may have taken it first — and a
     * package that redeclares a function of the application is a fatal error at boot rather than
     * a conflict somebody can work around. `php artisan webx:doctor` says when it is not ours.
     */
    function services(): ServiceQuery
    {
        return new ServiceQuery;
    }
}
