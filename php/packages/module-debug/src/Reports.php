<?php

declare(strict_types=1);

namespace WebxUi\Debug;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Config\Repository as Config;
use JsonException;

/**
 * Where reports are kept, for minutes (§9 of the debug bar spec).
 *
 * In a cache store under `webx:debug:<id>`, compressed JSON, gone when its TTL is — no table, no
 * migration, nothing to sweep by hand. Each administrator keeps the last `webx-debug.keep` of
 * them in an index of their own; an older one is forgotten when a newer one is written. A report
 * is only ever handed to the administrator who made it: anybody else, and an expired id, gets
 * nothing — the same nothing, so an id cannot be tried for existence.
 */
final class Reports
{
    private const PREFIX = 'webx:debug:';

    public function __construct(
        private readonly CacheFactory $cache,
        private readonly Config $config,
    ) {}

    /** A ULID: 26 characters of Crockford's base 32. */
    public static function isId(string $id): bool
    {
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id) === 1;
    }

    /**
     * @param  array<string, mixed>  $report  with `id`, `user_id`, `url`, `at` and `summary`
     */
    public function put(array $report): void
    {
        $id = (string) $report['id'];
        $user = (string) $report['user_id'];
        $store = $this->store();
        $ttl = $this->ttl();

        $store->put(self::PREFIX.$id, $this->pack($report), $ttl);

        $index = $this->index($user);
        array_unshift($index, [
            'id' => $id,
            'url' => $report['url'] ?? null,
            'at' => $report['at'] ?? null,
            'summary' => $report['summary'] ?? [],
        ]);

        $keep = max(1, (int) $this->config->get('webx-debug.keep', 20));

        foreach (array_slice($index, $keep) as $old) {
            $store->forget(self::PREFIX.$old['id']);
        }

        $store->put(self::PREFIX.'index:'.$user, array_slice($index, 0, $keep), $ttl);
    }

    /**
     * The report, if it is this administrator's and still kept.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $id, int|string $user): ?array
    {
        if (! self::isId($id)) {
            return null;
        }

        $packed = $this->store()->get(self::PREFIX.$id);
        $report = is_string($packed) ? $this->unpack($packed) : null;

        if ($report === null || (string) ($report['user_id'] ?? '') !== (string) $user) {
            return null;
        }

        return $report;
    }

    /**
     * This administrator's latest reports, newest first, those still kept.
     *
     * @return list<array{id: string, url: mixed, at: mixed, summary: mixed}>
     */
    public function recent(int|string $user): array
    {
        $store = $this->store();

        return array_values(array_filter(
            $this->index((string) $user),
            static fn (array $entry): bool => $store->has(self::PREFIX.$entry['id']),
        ));
    }

    /**
     * @return list<array{id: string, url: mixed, at: mixed, summary: mixed}>
     */
    private function index(string $user): array
    {
        $index = $this->store()->get(self::PREFIX.'index:'.$user);

        if (! is_array($index)) {
            return [];
        }

        return array_values(array_filter($index, static fn (mixed $entry): bool => is_array($entry) && is_string($entry['id'] ?? null)));
    }

    private function store(): Repository
    {
        $name = $this->config->get('webx-debug.store');

        return $this->cache->store(is_string($name) && $name !== '' ? $name : null);
    }

    private function ttl(): int
    {
        return max(1, (int) $this->config->get('webx-debug.ttl', 600));
    }

    /** @param  array<string, mixed>  $report */
    private function pack(array $report): string
    {
        $json = json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        // Base64 rather than raw bytes: a `database` store keeps its values in a text column.
        return base64_encode((string) gzdeflate((string) $json, 6));
    }

    /** @return array<string, mixed>|null */
    private function unpack(string $packed): ?array
    {
        $raw = base64_decode($packed, true);
        $json = $raw === false ? false : @gzinflate($raw);

        if (! is_string($json)) {
            return null;
        }

        try {
            $report = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($report) ? $report : null;
    }
}
