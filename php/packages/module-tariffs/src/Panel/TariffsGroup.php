<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Tariffs\TariffsServiceProvider;

/**
 * What the two sections of the tariffs have in common: the group of the menu they sit in (§5.2).
 *
 * The group is declared by {@see TariffsServiceProvider} in the panel's config at boot rather
 * than shipped as a default: a site that published `webx-admin.php` has its own copy of the list,
 * and a new default would never reach it.
 */
abstract class TariffsGroup extends AbstractModule
{
    /** The id of the navigation group. */
    public const GROUP = 'tariffs';

    public function group(): ?string
    {
        return self::GROUP;
    }
}
