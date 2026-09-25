<?php

declare(strict_types=1);

use WebxUi\Events\Rendering\EventQuery;

if (! function_exists('events')) {
    /**
     * `events()` — the events a template may show, as cards (see {@see EventQuery}).
     *
     *     events()->take(3);                         // the next three
     *     events()->past()->take(6);                 // the last six that are over
     *     events()->in('cooking-classes');
     *     events()->relatedTo('service', $service);
     *
     * Guarded, because a site may have taken the name first, and redeclaring it would be a fatal
     * error at boot. `php artisan webx:doctor` says when it is not ours.
     */
    function events(): EventQuery
    {
        return new EventQuery;
    }
}
