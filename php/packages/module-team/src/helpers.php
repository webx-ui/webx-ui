<?php

declare(strict_types=1);

use WebxUi\Team\Rendering\TeamQuery;

if (! function_exists('team')) {
    /**
     * `team()` — the people a template may show, as cards (see {@see TeamQuery}).
     *
     *     team()->take(6);
     *     team()->relatedTo('service', $service);
     *     team()->only([12, 7]);
     *     team()->except($member);
     *
     * Guarded, because the name is short enough that a site may have taken it first — and a
     * package that redeclares a function of the application is a fatal error at boot rather than
     * a conflict somebody can work around. `php artisan webx:doctor` says when it is not ours.
     */
    function team(): TeamQuery
    {
        return new TeamQuery;
    }
}
