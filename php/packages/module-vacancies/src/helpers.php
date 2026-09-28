<?php

declare(strict_types=1);

use WebxUi\Vacancies\Rendering\VacancyQuery;

if (! function_exists('vacancies')) {
    /**
     * `vacancies()` — the vacancies a template may show, as cards (see {@see VacancyQuery}).
     *
     *     vacancies()->take(6);                     // the first six open ones
     *     vacancies()->in('development');
     *     vacancies()->closed();
     *     vacancies()->groups();                    // a group per category, as the index has them
     *
     * Guarded, because a site may have taken the name first, and redeclaring it would be a fatal
     * error at boot. `php artisan webx:doctor` says when it is not ours.
     */
    function vacancies(): VacancyQuery
    {
        return new VacancyQuery;
    }
}
