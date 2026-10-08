<?php

declare(strict_types=1);

namespace WebxUi\Admin\Editing;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Who has a record open in the panel right now.
 *
 * Told by the editor itself: while a record's editor is on screen it says so every
 * {@see self::HEARTBEAT} seconds, and somebody who has not said it for {@see self::TTL} seconds
 * has closed the tab, lost the connection or gone to lunch — either way they are not about to
 * press «Save». What it is for is the agent's side: `pages_get` and `blocks_get_content` name who
 * is editing, so that an agent can tell its user before writing under somebody's cursor.
 *
 * Keyed by the record itself — its morph class and key — so that the panel's editor of a page
 * and an agent's `blocks_get_content` on the same page talk about the same thing whatever each
 * of them calls it.
 *
 * Kept in the cache rather than a table. It is a minute of truth about a screen, worth nothing
 * after a restart, and a row per heartbeat would be the busiest table of the panel.
 */
final class Presence
{
    /** How often the editor says it is still there. */
    public const HEARTBEAT = 20;

    /** How long a heartbeat counts: three missed ones and the editor is gone. */
    public const TTL = 60;

    public function __construct(private readonly Cache $cache) {}

    /** An administrator has this record open. */
    public function touch(Model $record, int $adminId, string $name): void
    {
        $now = Carbon::now()->getTimestamp();
        $present = $this->present($record);

        $present[$adminId] = [
            'name' => $name,
            'since' => $present[$adminId]['since'] ?? $now,
            'seen' => $now,
        ];

        $this->cache->put($this->key($record), $present, self::TTL * 2);
    }

    /** They closed it. */
    public function leave(Model $record, int $adminId): void
    {
        $present = $this->present($record);
        unset($present[$adminId]);

        $this->cache->put($this->key($record), $present, self::TTL * 2);
    }

    /**
     * Who has it open, first come first, leaving out the one asking.
     *
     * @return list<array{id: int, name: string, since: string, seen_at: string}>
     */
    public function of(Model $record, ?int $except = null): array
    {
        $present = $this->present($record);
        unset($present[$except ?? -1]);

        uasort($present, static fn (array $a, array $b): int => $a['since'] <=> $b['since']);

        $list = [];

        foreach ($present as $adminId => $entry) {
            $list[] = [
                'id' => $adminId,
                'name' => $entry['name'],
                'since' => Carbon::createFromTimestamp($entry['since'])->toAtomString(),
                'seen_at' => Carbon::createFromTimestamp($entry['seen'])->toAtomString(),
            ];
        }

        return $list;
    }

    /**
     * The entries still alive.
     *
     * @return array<int, array{name: string, since: int, seen: int}>
     */
    private function present(Model $record): array
    {
        $stored = $this->cache->get($this->key($record));
        $cutoff = Carbon::now()->getTimestamp() - self::TTL;
        $alive = [];

        foreach (is_array($stored) ? $stored : [] as $adminId => $entry) {
            if (is_array($entry) && is_int($entry['seen'] ?? null) && $entry['seen'] >= $cutoff) {
                $alive[(int) $adminId] = [
                    'name' => (string) ($entry['name'] ?? ''),
                    'since' => (int) ($entry['since'] ?? $entry['seen']),
                    'seen' => $entry['seen'],
                ];
            }
        }

        return $alive;
    }

    private function key(Model $record): string
    {
        return 'webx-admin.presence.'.$record->getMorphClass().'.'.$record->getKey();
    }
}
