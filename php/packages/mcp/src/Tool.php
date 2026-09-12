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
 * token has to hold.
 */
final class Tool
{
    public const DRY_RUN = 'dry_run';

    /**
     * @param  array<string, mixed>  $inputSchema  JSON Schema for the arguments
     */
    private function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly bool $mutating,
        public readonly ?string $scope,
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
     */
    public static function read(
        string $name,
        string $description,
        Closure $handler,
        array $inputSchema = [],
        ?string $scope = null,
    ): self {
        return new self($name, $description, self::normaliseSchema($inputSchema), false, $scope, $handler);
    }

    /**
     * A tool that changes something. `dry_run` is added to its arguments here so that every
     * such tool can be asked what it would do, without each author having to remember.
     *
     * @param  array<string, mixed>  $inputSchema
     */
    public static function mutating(
        string $name,
        string $description,
        Closure $handler,
        array $inputSchema = [],
        ?string $scope = null,
    ): self {
        $schema = self::normaliseSchema($inputSchema);
        $schema['properties'][self::DRY_RUN] = [
            'type' => 'boolean',
            'description' => 'Report what would change instead of changing it.',
            'default' => false,
        ];

        return new self($name, $description, $schema, true, $scope, $handler);
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
}
