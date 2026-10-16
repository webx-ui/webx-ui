<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Output;

use Illuminate\Contracts\Cache\Repository as Cache;
use WebxUi\Snippets\Models\Snippet;

/**
 * Every enabled snippet, grouped by place, as one cache entry with no expiry (§5.7).
 *
 * What a request filters — its path, its language, the environment — is not part of the key:
 * the list is small, and caching each combination would multiply it for nothing. A place taken
 * out of the config is not filtered here either: the printer asks for declared places only.
 *
 * Forgotten by the model's own events and by a reorder, which writes past them.
 */
final class Codes
{
    public const KEY = 'webx-snippets.codes';

    public function __construct(private readonly Cache $cache) {}

    /**
     * @return list<array{id: int, code: string, consent: string, only_paths: list<string>, except_paths: list<string>, locales: list<string>, on_stage: bool}>
     */
    public function of(string $place): array
    {
        return $this->all()[$place] ?? [];
    }

    /**
     * @return array<string, list<array{id: int, code: string, consent: string, only_paths: list<string>, except_paths: list<string>, locales: list<string>, on_stage: bool}>>
     */
    public function all(): array
    {
        /** @var array<string, list<array{id: int, code: string, consent: string, only_paths: list<string>, except_paths: list<string>, locales: list<string>, on_stage: bool}>> $all */
        $all = $this->cache->rememberForever(self::KEY, static function (): array {
            $places = [];

            $snippets = Snippet::query()
                ->where('enabled', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            foreach ($snippets as $snippet) {
                $places[$snippet->place][] = [
                    'id' => (int) $snippet->id,
                    'code' => $snippet->code,
                    'consent' => $snippet->consent,
                    'only_paths' => $snippet->only_paths ?? [],
                    'except_paths' => $snippet->except_paths ?? [],
                    'locales' => $snippet->locales ?? [],
                    'on_stage' => $snippet->on_stage,
                ];
            }

            return $places;
        });

        return $all;
    }

    public function forget(): void
    {
        $this->cache->forget(self::KEY);
    }
}
