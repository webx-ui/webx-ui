<?php

declare(strict_types=1);

use WebxUi\Tariffs\Rendering\TariffQuery;

if (! function_exists('tariffs')) {
    /**
     * `tariffs()` — the tariffs a template may show, as cards (see {@see TariffQuery}).
     *
     *     tariffs()->in($groups)->take(3);
     *     tariffs()->relatedTo('service', $service);
     *     tariffs()->only([7, 3]);
     *     tariffs()->categories();
     *
     * Guarded, because the name is short enough that a site may have taken it first — and a
     * package that redeclares a function of the application is a fatal error at boot rather than
     * a conflict somebody can work around. `php artisan webx:doctor` says when it is not ours.
     */
    function tariffs(): TariffQuery
    {
        return new TariffQuery;
    }
}
