<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests\Fixtures;

use Illuminate\Auth\GenericUser;

/**
 * A user that came in with a token, the way Sanctum's `HasApiTokens` would present one: the
 * two methods `Scopes` asks for, and nothing of Sanctum itself.
 */
final class TokenUser extends GenericUser
{
    /**
     * @param  list<string>  $abilities
     */
    public function __construct(private readonly array $abilities)
    {
        parent::__construct(['id' => 1, 'name' => 'Agent']);
    }

    public function currentAccessToken(): object
    {
        return (object) ['abilities' => $this->abilities];
    }

    public function tokenCan(string $ability): bool
    {
        return in_array('*', $this->abilities, true) || in_array($ability, $this->abilities, true);
    }
}
