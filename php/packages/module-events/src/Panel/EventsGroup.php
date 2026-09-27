<?php

declare(strict_types=1);

namespace WebxUi\Events\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Events\EventsServiceProvider;

/**
 * What the two sections of the events have in common: the group they sit in (§4.9) —
 * Events · Categories.
 *
 * The group itself is declared by {@see EventsServiceProvider} in the panel's config at boot
 * rather than shipped as a default: a site that published `webx-admin.php` has its own copy of
 * the list, and a new default would never reach it.
 */
abstract class EventsGroup extends AbstractModule
{
    /** The id of the navigation group, and the first segment of every permission below. */
    public const GROUP = 'events';

    public function group(): ?string
    {
        return self::GROUP;
    }
}
