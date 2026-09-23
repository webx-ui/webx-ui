<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests\Fixtures;

use Illuminate\Auth\GenericUser;

/**
 * A user that came in with a key naming module scopes — the shape a token takes when it was
 * issued for a machine rather than granted through the OAuth flow. Two methods, and nothing
 * of whatever issued it.
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
