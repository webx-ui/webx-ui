<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests\Fixtures;

use Laravel\Passport\Contracts\ScopeAuthorizable;

/**
 * A token that names module scopes — the shape one takes when it was issued for a machine
 * rather than granted through the OAuth flow, where a client is offered `mcp:use` and nothing
 * finer.
 */
final readonly class Scoped implements ScopeAuthorizable
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(private array $scopes) {}

    public function can(string $scope): bool
    {
        return in_array('*', $this->scopes, true) || in_array($scope, $this->scopes, true);
    }

    public function cant(string $scope): bool
    {
        return ! $this->can($scope);
    }
}
