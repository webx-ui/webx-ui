<?php

declare(strict_types=1);

namespace WebxUi\Admin\Editing;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use WebxUi\Admin\History\HistoryContext;

/**
 * What happened to a record that is not a change of its content: published, taken off the site,
 * its draft thrown away, an old version put back, moved in the tree, put in the bin or taken out,
 * deleted for good.
 *
 * The revision an open editor holds is a hash of the content, and none of these change it — or
 * change it without saying why. Publishing left a second editor showing «Edited» on a page that
 * was on the site; a move left it showing the old address; a page in the bin was found out only
 * by a save that failed. The heartbeat hands these out beside the revision, each with who did it
 * and through which door, so the editor can say «Owner published the page at 16:40».
 *
 * Kept in the cache like {@see Presence}: it is for editors open right now, and an hour of it is
 * more than any of them needs. Noted after the transaction commits, so that a dry run — the real
 * change, rolled back — leaves nothing behind.
 */
final class RecordEvents
{
    public const PUBLISHED = 'published';

    public const UNPUBLISHED = 'unpublished';

    public const DISCARDED = 'discarded';

    public const RESTORED_VERSION = 'restored_version';

    public const MOVED = 'moved';

    public const TRASHED = 'trashed';

    public const RESTORED = 'restored';

    /**
     * Deleted for good. Noted like the rest, and it outlives the row it is about: the record is
     * gone, and this is the only thing left that can tell an open editor who did it and when.
     */
    public const PURGED = 'purged';

    /** How many are kept per record: the newest ones. */
    private const KEEP = 10;

    /** How long they are kept. */
    private const TTL = 3600;

    public function __construct(
        private readonly Cache $cache,
        private readonly HistoryContext $context,
    ) {}

    /**
     * @param  array<string, mixed>  $detail  What the sentence needs beyond the kind: a version number.
     */
    public function note(Model $record, string $kind, array $detail = []): void
    {
        $event = [
            // Ordered within a second too: two events of one call — restore, then publish —
            // have to come out in the order they happened.
            'id' => (int) floor(microtime(true) * 1000000),
            'kind' => $kind,
            'author' => $this->context->adminName() ?: null,
            'author_id' => $this->context->adminId(),
            'source' => $this->context->source(),
            'at' => Carbon::now()->toAtomString(),
            'detail' => $detail === [] ? null : $detail,
        ];

        $write = function () use ($record, $event): void {
            $events = $this->of($record);
            $last = end($events);

            if (is_array($last) && $last['id'] >= $event['id']) {
                $event['id'] = $last['id'] + 1;
            }

            $events[] = $event;

            $this->cache->put($this->key($record), array_slice($events, -self::KEEP), self::TTL);
        };

        // Not now if a transaction is open: a rehearsal rolls back, and the cache does not.
        $record->getConnection()->afterCommit($write);
    }

    /**
     * Oldest first.
     *
     * @return list<array{id: int, kind: string, author: string|null, author_id: int|null, source: string, at: string, detail: array<string, mixed>|null}>
     */
    public function of(Model $record): array
    {
        return $this->stored($this->key($record));
    }

    /**
     * How a record that no longer exists went, when it was deleted for good within the hour.
     * Asked by its class and key, because there is no model left to ask with.
     *
     * @param  class-string<Model>  $class
     * @return array{id: int, kind: string, author: string|null, author_id: int|null, source: string, at: string, detail: array<string, mixed>|null}|null
     */
    public function purged(string $class, int|string $id): ?array
    {
        $events = $this->stored('webx-admin.record-events.'.(new $class)->getMorphClass().'.'.$id);

        foreach (array_reverse($events) as $event) {
            if (($event['kind'] ?? null) === self::PURGED) {
                return $event;
            }
        }

        return null;
    }

    /** @return list<array{id: int, kind: string, author: string|null, author_id: int|null, source: string, at: string, detail: array<string, mixed>|null}> */
    private function stored(string $key): array
    {
        $stored = $this->cache->get($key);

        return is_array($stored) ? array_values(array_filter($stored, is_array(...))) : [];
    }

    private function key(Model $record): string
    {
        return 'webx-admin.record-events.'.$record->getMorphClass().'.'.$record->getKey();
    }
}
