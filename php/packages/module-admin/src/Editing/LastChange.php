<?php

declare(strict_types=1);

namespace WebxUi\Admin\Editing;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Support\Authors;
use WebxUi\Admin\Versions\EntityVersion;

/**
 * Who wrote a record last, through which door, and when — read off its newest version.
 *
 * Every save of a draft leaves an autosave and every publication a version, each with its author
 * and its source, so the newest one is the last change. What the panel says with it is the part
 * a bare «the page changed» leaves out: whether that was a colleague in the panel or an agent
 * over MCP, which is the difference between asking across the room and reading what the agent
 * did.
 */
final class LastChange
{
    /**
     * @param  mixed  $reader  Whoever is asking: their model is the table the author is named from.
     * @return array{author: string|null, author_id: int|null, source: string, at: string|null}|null
     */
    public static function of(Model $entity, mixed $reader = null): ?array
    {
        if (! method_exists($entity, 'versions')) {
            return null;
        }

        $version = $entity->versions()->first();

        if (! $version instanceof EntityVersion) {
            return null;
        }

        $authorId = $version->author_id;
        $names = $authorId === null ? [] : Authors::names($reader, [$authorId]);

        return [
            'author' => $authorId === null ? null : ($names[$authorId] ?? null),
            'author_id' => $authorId,
            'source' => $version->source,
            'at' => $version->created_at?->toAtomString(),
        ];
    }
}
