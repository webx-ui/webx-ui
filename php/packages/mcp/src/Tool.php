<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use Closure;
use WebxUi\Mcp\Exceptions\McpException;

/**
 * One thing an agent can do with a module.
 *
 * Built through {@see self::read()} or {@see self::mutating()} rather than a constructor,
 * because the difference between the two is the whole safety story: a mutating tool is given
 * a `dry_run` argument whether its author remembered one or not, and carries a scope that a
 * token has to hold and a permission that the administrator has to hold.
 *
 * The handler is `fn (array $arguments, ?Authenticatable $user = null)`: the arguments as the
 * agent sent them, and the administrator the call acts as — null on the local stdio server,
 * where there is no request. A handler that declares one parameter is fine; a refusal the
 * agent should read is a thrown {@see Exceptions\ToolFailure}.
 */
final class Tool
{
    public const DRY_RUN = 'dry_run';

    /**
     * @param  array<string, mixed>  $inputSchema  JSON Schema for the arguments
     * @param  list<string>|null  $permissions  panel permissions, any of which lets the caller use the tool; null for the module's default
     */
    private function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly bool $mutating,
        public readonly ?string $scope,
        public readonly ?array $permissions,
        public readonly Closure $handler,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw McpException::invalidName($name);
        }

        if (trim($description) === '') {
            throw McpException::emptyDescription($name);
        }
    }

    /**
     * A tool that only looks.
     *
     * @param  array<string, mixed>  $inputSchema
     * @param  string|list<string>|null  $permission  the panel permission this tool is behind, when it is not the module's `<id>.view`; several mean any of them, the way a route's `cms.can` does
     */
    public static function read(
        string $name,
        string $description,
        Closure $handler,
        array $inputSchema = [],
        ?string $scope = null,
        string|array|null $permission = null,
    ): self {
        return new self(
            $name,
            $description,
            self::normaliseSchema($inputSchema),
            false,
            $scope,
            self::normalisePermission($name, $permission),
            $handler,
        );
    }

    /**
     * A tool that changes something. `dry_run` is added to its arguments here so that every
     * such tool can be asked what it would do, without each author having to remember.
     *
     * @param  array<string, mixed>  $inputSchema
     * @param  string|list<string>|null  $permission  the panel permission this tool is behind, when it is not the module's `<id>.manage`; several mean any of them
     */
    public static function mutating(
        string $name,
        string $description,
        Closure $handler,
        array $inputSchema = [],
        ?string $scope = null,
        string|array|null $permission = null,
    ): self {
        $schema = self::normaliseSchema($inputSchema);
        $schema['properties'][self::DRY_RUN] = [
            'type' => 'boolean',
            'description' => 'Report what would change instead of changing it.',
            'default' => false,
        ];

        return new self(
            $name,
            $description,
            $schema,
            true,
            $scope,
            self::normalisePermission($name, $permission),
            $handler,
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function isDryRun(array $arguments): bool
    {
        return (bool) ($arguments[self::DRY_RUN] ?? false);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function normaliseSchema(array $schema): array
    {
        $schema['type'] ??= 'object';
        $schema['properties'] ??= [];

        return $schema;
    }

    /**
     * One name or several, as a list — and never an empty one: a tool "behind no permission"
     * would be open to everybody, which is not what a forgotten argument should mean.
     *
     * @param  string|list<string>|null  $permission
     * @return list<string>|null
     */
    private static function normalisePermission(string $name, string|array|null $permission): ?array
    {
        if ($permission === null) {
            return null;
        }

        $permissions = array_values(array_filter(
            is_string($permission) ? [$permission] : $permission,
            static fn (string $one): bool => trim($one) !== '',
        ));

        if ($permissions === []) {
            throw McpException::emptyPermission($name);
        }

        return $permissions;
    }
}
