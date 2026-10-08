<?php

declare(strict_types=1);

namespace WebxUi\Admin\Editing;

use WebxUi\Mcp\Exceptions\ToolFailure;

/**
 * The rule an agent's write to a drafted record goes by: read first, then write what you read.
 *
 * The panel's own save may leave the revision out — an import, a script, a request that never
 * read the record — and is let through, because there is no editor on the other side of it to
 * surprise. An agent is different: it writes while people are editing, and its write without a
 * revision is exactly the one that lands on top of an editor's half-typed paragraph. So over MCP
 * a write names the revision the read gave it, or it says `force: true` and means it.
 *
 * A dry run needs neither: it writes nothing, and asking for a revision before an agent may
 * even rehearse would only teach it to read twice.
 */
final class AgentRevision
{
    /**
     * The `revision` and `force` properties of a tool's schema.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function properties(string $readTool): array
    {
        return [
            'revision' => [
                'type' => 'string',
                'description' => "The revision {$readTool} returned. Required: a write is refused when the record changed since, or when no revision is sent.",
            ],
            'force' => [
                'type' => 'boolean',
                'description' => 'Write without a revision, over whatever happened since. For scripts that mean to overwrite; an agent working beside people sends the revision instead.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  string  $what  The record as the sentence names it: "page", "entity", "service".
     *
     * @throws ToolFailure
     */
    public static function check(array $arguments, string $current, string $what, string $readTool): void
    {
        $sent = $arguments['revision'] ?? null;

        if (! is_string($sent) || $sent === '') {
            if (($arguments['force'] ?? false) === true || ($arguments['dry_run'] ?? false) === true) {
                return;
            }

            throw new ToolFailure(
                "Read the {$what} first: send the revision {$readTool} returns (it is [{$current}] now). "
                ."A write without one goes in over whatever an editor did since — {$readTool} says in being_edited_by "
                .'who has it open in the panel right now. A script that means to overwrite passes force: true.'
            );
        }

        if ($sent !== $current) {
            throw new ToolFailure(
                "The {$what} changed since you read it: revision is [{$current}], you sent [{$sent}]. "
                ."Read it again with {$readTool} and redo the edit on what is there now."
            );
        }
    }
}
