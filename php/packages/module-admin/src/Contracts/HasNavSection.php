<?php

declare(strict_types=1);

namespace WebxUi\Admin\Contracts;

/**
 * A module that stands under a caption inside its navigation group rather than among the
 * group's plain entries: the catalogue's labels and stock under «Dictionaries».
 *
 * A contract of its own rather than a method of {@see Module}: a project may implement `Module`
 * without `AbstractModule`, and a new method there would break it for a caption it never wanted.
 * The section is declared by the group in `webx-admin.groups` (`sections`); one the group does
 * not declare is ignored and the entry stays with the plain ones.
 */
interface HasNavSection
{
    /** The id of a section of this module's group. */
    public function navSection(): string;
}
