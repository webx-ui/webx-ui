<?php

declare(strict_types=1);

namespace WebxUi\Faq\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Faq\FaqServiceProvider;

/**
 * What the two sections of the FAQ have in common: the group they sit in (§4.5).
 *
 * The group is declared by {@see FaqServiceProvider} in the panel's config at boot rather than
 * shipped as a default: a site that published `webx-admin.php` has its own copy of the list, and
 * a new default would never reach it.
 */
abstract class FaqGroup extends AbstractModule
{
    /** The id of the navigation group, and the first segment of every permission below. */
    public const GROUP = 'faq';

    public function group(): ?string
    {
        return self::GROUP;
    }
}
