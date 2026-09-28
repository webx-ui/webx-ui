<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Vacancies\VacanciesServiceProvider;

/**
 * What the two sections of the vacancies have in common: the group they sit in (§4.10) —
 * Vacancies · Categories.
 *
 * The group itself is declared by {@see VacanciesServiceProvider} in the panel's config at boot
 * rather than shipped as a default: a site that published `webx-admin.php` has its own copy of
 * the list, and a new default would never reach it.
 */
abstract class VacanciesGroup extends AbstractModule
{
    /** The id of the navigation group, and the first segment of every permission below. */
    public const GROUP = 'vacancies';

    public function group(): ?string
    {
        return self::GROUP;
    }
}
