<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests\Fixtures;

use Laravel\Passport\Contracts\ScopeAuthorizable;

/**
 * A key issued for a program in the panel or by `webx:mcp:token`: a token with an id that
 * `mcp_keys` knows, carrying whatever scopes it was issued with — `mcp:use` included, which is
 * what a key issued without scopes carries and what an OAuth connection carries too.
 */
final readonly class Keyed implements ScopeAuthorizable
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(private string $id, private array $scopes) {}

    public function getKey(): string
    {
        return $this->id;
    }

    public function can(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function cant(string $scope): bool
    {
        return ! $this->can($scope);
    }
}
