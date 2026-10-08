<?php

declare(strict_types=1);

namespace WebxUi\Admin\Editing;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
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
     * The same two properties for a tool that changes the state of a record ({@see guard()}).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function stateProperties(string $readTool): array
    {
        return [
            'revision' => [
                'type' => 'string',
                'description' => "The revision {$readTool} returned. Refused when the record changed since; required while somebody has the record open in the panel ({$readTool} says who in being_edited_by).",
            ],
            'force' => [
                'type' => 'boolean',
                'description' => 'Go ahead without a revision even though somebody has the record open.',
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

    /**
     * The rule for what changes a record's state rather than its content — publish, unpublish,
     * discard, a version put back, a move, the bin.
     *
     * Each of these acts on whatever the draft holds right now, which may be an edit the agent
     * never read: publishing put a colleague's half-made change on the site. So a revision that
     * is sent has to be the current one, as for a write; and while somebody has the record open
     * in the panel, one has to be sent — or `force: true` — and the refusal names who it is.
     * With nobody there the call goes through without one, as it always did: there is nobody to
     * surprise, and a script that publishes a hundred records need not read each first.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws ToolFailure
     */
    public static function guard(array $arguments, string $current, Model $record, string $what, string $readTool): void
    {
        $sent = $arguments['revision'] ?? null;

        if (is_string($sent) && $sent !== '') {
            self::check($arguments, $current, $what, $readTool);

            return;
        }

        if (($arguments['force'] ?? false) === true || ($arguments['dry_run'] ?? false) === true) {
            return;
        }

        $editors = Container::getInstance()->make(Presence::class)->of($record);

        if ($editors === []) {
            return;
        }

        $names = implode(', ', array_map(
            static fn (array $editor): string => $editor['name'] !== '' ? $editor['name'] : "administrator #{$editor['id']}",
            $editors,
        ));

        throw new ToolFailure(
            "{$names} ".(count($editors) === 1 ? 'has' : 'have')." this {$what} open in the panel right now. "
            ."Read it with {$readTool} and send the revision it returns (it is [{$current}] now), so that this "
            .'acts on what you read and not on an edit you have not seen — and tell your user who is editing. '
            .'force: true goes ahead regardless.'
        );
    }
}
