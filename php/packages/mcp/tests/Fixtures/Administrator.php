<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests\Fixtures;

use Illuminate\Auth\GenericUser;
use WebxUi\Admin\Contracts\HasPermissions;

/**
 * Somebody signed in to the panel, as far as the server can tell: a user that can be asked
 * which permissions they hold. No token — what is being checked through this one is the
 * third question, not the first two.
 */
final class Administrator extends GenericUser implements HasPermissions
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(private readonly array $permissions)
    {
        parent::__construct(['id' => 3, 'name' => 'Editor']);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
