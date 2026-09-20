<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Blog\BlogServiceProvider;

/**
 * What the three sections of the blog have in common: the group they sit in.
 *
 * Three sections and not one, because the panel's navigation is one entry per module and the
 * blog wants three of them — articles, rubrics, tags. Tags are a section of their own rather
 * than a tab of rubrics for a reason that is about how they are used and not about what they
 * are: rubrics are edited once a quarter, tags are raked through after every burst of writing
 * (§10).
 *
 * The group itself is declared by {@see BlogServiceProvider}, in the panel's
 * config, because that is where `module-admin` reads groups from — and it is added at boot
 * rather than shipped as a default, since a site that published `webx-admin.php` has its own
 * copy of the list and a new default would never reach it (CLAUDE.md §4).
 */
abstract class BlogModule extends AbstractModule
{
    /** The id of the navigation group, and the first segment of every permission below. */
    public const GROUP = 'blog';

    public function group(): ?string
    {
        return self::GROUP;
    }
}
