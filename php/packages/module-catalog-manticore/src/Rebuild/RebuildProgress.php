<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Rebuild;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;

/**
 * Where the rebuild started from the panel stands (decision 27): queued, running with so many
 * written of so many, done or failed. Kept in the cache, which the web and the worker share — a
 * rebuild is one at a time and its last word is all anybody asks for.
 *
 * A rebuild nobody has heard of for a quarter of an hour is stalled: the worker died or there is
 * none. The panel says so and lets a person start it again, rather than waiting forever.
 */
final class RebuildProgress
{
    public const IDLE = 'idle';

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    /** Seconds without a word before a queued or running rebuild counts as stalled. */
    public const STALLED_AFTER = 900;

    private const KEY = 'webx-catalog-manticore.rebuild';

    public function __construct(private readonly Cache $cache) {}

    /**
     * @return array{state: string, done: int, total: int, queued_at: string|null, started_at: string|null, finished_at: string|null, error: string|null, stalled: bool}
     */
    public function get(): array
    {
        $stored = $this->cache->get(self::KEY);
        $stored = is_array($stored) ? $stored : [];
        $state = is_string($stored['state'] ?? null) ? $stored['state'] : self::IDLE;
        $heard = (int) ($stored['heard'] ?? 0);

        return [
            'state' => $state,
            'done' => (int) ($stored['done'] ?? 0),
            'total' => (int) ($stored['total'] ?? 0),
            'queued_at' => self::string($stored['queued_at'] ?? null),
            'started_at' => self::string($stored['started_at'] ?? null),
            'finished_at' => self::string($stored['finished_at'] ?? null),
            'error' => self::string($stored['error'] ?? null),
            'stalled' => in_array($state, [self::QUEUED, self::RUNNING], true) && $heard < Carbon::now()->getTimestamp() - self::STALLED_AFTER,
        ];
    }

    /** Whether a rebuild is waiting or running and still heard from: another would race it. */
    public function busy(): bool
    {
        $progress = $this->get();

        return in_array($progress['state'], [self::QUEUED, self::RUNNING], true) && ! $progress['stalled'];
    }

    public function queue(): void
    {
        $this->put(['state' => self::QUEUED, 'done' => 0, 'total' => 0, 'queued_at' => self::now()]);
    }

    public function start(): void
    {
        $this->put(['state' => self::RUNNING, 'started_at' => self::now()] + $this->stored());
    }

    public function advance(int $done, int $total): void
    {
        $this->put(['state' => self::RUNNING, 'done' => $done, 'total' => $total] + $this->stored());
    }

    public function finish(int $done): void
    {
        $this->put(['state' => self::DONE, 'done' => $done, 'total' => max($done, (int) ($this->stored()['total'] ?? 0)), 'finished_at' => self::now()] + $this->stored());
    }

    public function fail(string $error): void
    {
        $this->put(['state' => self::FAILED, 'error' => $error, 'finished_at' => self::now()] + $this->stored());
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        $stored = $this->cache->get(self::KEY);

        return is_array($stored) ? $stored : [];
    }

    /** @param array<string, mixed> $progress */
    private function put(array $progress): void
    {
        // A week: long enough for «done yesterday» to still be said, short enough to go away.
        $this->cache->put(self::KEY, ['heard' => Carbon::now()->getTimestamp()] + $progress, 7 * 24 * 3600);
    }

    private static function now(): string
    {
        return Carbon::now()->toIso8601String();
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
