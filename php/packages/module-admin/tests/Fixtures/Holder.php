<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use Illuminate\Auth\GenericUser;
use WebxUi\Admin\Access\HoldsPermissions;

/**
 * An account that says what its roles grant, the way the auth module's administrator does —
 * and, when given one, the token it came with.
 */
final class Holder extends GenericUser implements HoldsPermissions
{
    /**
     * @param  list<string>  $granted
     * @param  list<string>|null  $scopes  the token's, or null for somebody signed in by session
     */
    public function __construct(
        private readonly array $granted = [],
        private readonly bool $super = false,
        private readonly ?array $scopes = null,
    ) {
        parent::__construct(['id' => 1]);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->super || in_array($permission, $this->granted, true);
    }

    public function isSuperAdministrator(): bool
    {
        return $this->super;
    }

    /**
     * @return list<string>
     */
    public function grantedPermissions(): array
    {
        return $this->super ? [] : $this->granted;
    }

    public function token(): ?object
    {
        return $this->scopes === null ? null : (object) ['scopes' => $this->scopes];
    }

    public function tokenCan(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
