<?php

declare(strict_types=1);

namespace WebxUi\Services\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Services\ServicesServiceProvider;

/**
 * What the two sections of the catalogue have in common: the group they sit in (§4.6).
 *
 * Two sections rather than one with a tab, because the navigation is one entry per module, and
 * the services are opened every week while the categories are edited once a quarter.
 *
 * The group itself is declared by {@see ServicesServiceProvider} in the panel's config at boot
 * rather than shipped as a default: a site that published `webx-admin.php` has its own copy of
 * the list, and a new default would never reach it.
 */
abstract class ServicesGroup extends AbstractModule
{
    /** The id of the navigation group, and the first segment of every permission below. */
    public const GROUP = 'services';

    public function group(): ?string
    {
        return self::GROUP;
    }
}
