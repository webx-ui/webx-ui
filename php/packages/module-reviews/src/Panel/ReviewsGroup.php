<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Reviews\ReviewsServiceProvider;

/**
 * What the two sections of the reviews have in common: the group they sit in (§4.6).
 *
 * The group is declared by {@see ReviewsServiceProvider} in the panel's config at boot rather
 * than shipped as a default: a site that published `webx-admin.php` has its own copy of the list,
 * and a new default would never reach it.
 */
abstract class ReviewsGroup extends AbstractModule
{
    /** The id of the navigation group, and the first segment of every permission below. */
    public const GROUP = 'reviews';

    public function group(): ?string
    {
        return self::GROUP;
    }
}
