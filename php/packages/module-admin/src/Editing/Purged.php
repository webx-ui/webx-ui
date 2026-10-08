<?php

declare(strict_types=1);

namespace WebxUi\Admin\Editing;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * The answer about a record that was deleted for good: 410, with who did it and when.
 *
 * A 404 is what a record nobody has saved yet answers too, and what a network that blinked
 * looks like to an editor that swallows it — so an editor open on a purged page stayed silent
 * until a save failed with nowhere for the text to go. Gone is said as gone: the heartbeat, a
 * save and a publication all answer this, and the editor says it in one line and keeps the form.
 */
final class Purged
{
    /**
     * @param  array{author: string|null, source: string, at: string}  $event  The `purged` event from {@see RecordEvents}.
     */
    public static function answer(array $event): JsonResponse
    {
        return new JsonResponse([
            'message' => self::message($event),
            'gone' => $event,
        ], 410);
    }

    /**
     * @param  array{author: string|null, source: string, at: string}  $event
     */
    private static function message(array $event): string
    {
        $name = $event['author'] ?? (string) __('webx-admin::editing.somebody');

        $who = match ($event['source']) {
            'mcp' => (string) __('webx-admin::editing.via-agent', ['name' => $name]),
            'import' => (string) __('webx-admin::editing.via-import', ['name' => $name]),
            default => $name,
        };

        return (string) __('webx-admin::editing.purged-message', [
            'who' => $who,
            'when' => Carbon::parse($event['at'])->format('Y-m-d H:i'),
        ]);
    }
}
