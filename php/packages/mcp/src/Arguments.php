<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use WebxUi\Mcp\Exceptions\ToolFailure;

/**
 * Arguments a tool does not take are refused, not dropped.
 *
 * An agent that misspells `titel`, or sends a field the editor does not have, and gets a cheerful
 * answer believes it wrote something. The refusal is worded the way `blocks_edit_content` words
 * one for a field a block type does not have, so an agent learns one shape of it.
 */
final class Arguments
{
    /**
     * The same tool, with a door in front of its handler that refuses every argument its schema
     * does not name.
     *
     * @param  string  $prefix  What the server puts in front of the tool's name (`services_`), so
     *                          the refusal names the tool as the agent called it.
     */
    public static function strict(Tool $tool, string $prefix = ''): Tool
    {
        $properties = $tool->inputSchema['properties'] ?? [];
        $known = array_map(strval(...), array_keys(is_array($properties) ? $properties : []));
        $name = $prefix.$tool->name;
        $handler = $tool->handler;

        $guarded = static function (array $arguments, ?Authenticatable $user = null) use ($known, $name, $handler): mixed {
            self::refuseUnknown($arguments, $known, $name, 'argument');

            return $handler($arguments, $user);
        };

        return $tool->mutating
            ? Tool::mutating($tool->name, $tool->description, $guarded, $tool->inputSchema, $tool->scope, $tool->permissions)
            : Tool::read($tool->name, $tool->description, $guarded, $tool->inputSchema, $tool->scope, $tool->permissions);
    }

    /**
     * Every tool of a list made strict.
     *
     * @param  list<Tool>  $tools
     * @return list<Tool>
     */
    public static function strictAll(array $tools, string $prefix = ''): array
    {
        return array_values(array_map(static fn (Tool $tool): Tool => self::strict($tool, $prefix), $tools));
    }

    /**
     * @param  array<array-key, mixed>  $given
     * @param  list<string>  $known
     * @param  string  $what  Whose fields these are: a tool's name, a block type.
     * @param  string  $noun  `field` or `argument`.
     *
     * @throws ToolFailure when a key is not one of the known ones
     */
    public static function refuseUnknown(array $given, array $known, string $what, string $noun = 'field'): void
    {
        $unknown = array_values(array_diff(array_map(strval(...), array_keys($given)), $known));

        if ($unknown === []) {
            return;
        }

        throw new ToolFailure(
            "{$what} has no {$noun} [".implode('], [', $unknown).']. Its '.$noun.'s: '
            .($known === [] ? 'none' : implode(', ', $known)).'.'
        );
    }
}
