<?php

declare(strict_types=1);

namespace WebxUi\Admin\Contracts;

/**
 * A module that names its own permissions in the ready-made roles of the panel
 * (WEBX_UI_PERMISSIONS.md §12, fork 4): a site's own module, or any module the list in
 * `module-auth` does not know, gets into `editor`, `seo` or `developer` without that list being
 * edited.
 *
 * Optional: without it a module reaches only `viewer`, by its `.view` permissions. What it names
 * is added to the role's definition and then goes the way of everything there — created with the
 * role, topped up into a role made before the module, never brought back once the owner took it
 * away. Only the module's own `permissions()` count, only in those three roles, and never
 * `admins.*`, `roles.*`, `tasks.*` or `debug.*`; anything else is ignored.
 *
 * A contract of its own rather than a method of {@see Module}, for the reason
 * {@see HasNavSection} gives: a project may implement `Module` directly.
 */
interface HasDefaultRoles
{
    /**
     * The module's permissions by the code of a ready-made role: `['editor' => ['offices.view',
     * 'offices.manage'], 'seo' => ['offices.view']]`.
     *
     * @return array<string, list<string>>
     */
    public function defaultRoles(): array;
}
