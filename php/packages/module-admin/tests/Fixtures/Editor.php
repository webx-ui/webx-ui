<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use Illuminate\Auth\GenericUser;
use WebxUi\Admin\Contracts\HasPermissions;

/** Somebody signed in, with a fixed set of permissions — no auth module needed. */
final class Editor extends GenericUser implements HasPermissions
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(private readonly array $permissions = [])
    {
        parent::__construct(['id' => 1, 'name' => 'Editor']);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
