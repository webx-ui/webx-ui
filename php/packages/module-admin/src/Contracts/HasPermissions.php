<?php

declare(strict_types=1);

namespace WebxUi\Admin\Contracts;

/**
 * Whoever is signed in, as far as a screen is concerned: something that can be asked whether a
 * permission is granted. The auth module's administrator implements it; this package never
 * learns what an administrator is.
 */
interface HasPermissions
{
    public function hasPermission(string $permission): bool;
}
