<?php

declare(strict_types=1);

use WebxUi\Press\Rendering\PressQuery;

if (! function_exists('press')) {
    /**
     * `press()` — the outlets and the articles a template may show, as cards (see
     * {@see PressQuery}).
     *
     *     press()->featured()->take(12);
     *     press()->kind('interview');
     *     press()->articles()->take(6);
     *
     * Guarded, because the name is short enough that a site may have taken it first — and a
     * package that redeclares a function of the application is a fatal error at boot rather than
     * a conflict somebody can work around. `php artisan webx:doctor` says when it is not ours.
     */
    function press(): PressQuery
    {
        return new PressQuery;
    }
}
