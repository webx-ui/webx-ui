<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

/**
 * What {@see StrayValues} found in one entity, or took out of it.
 *
 * `draftDropped`: once the stray values are gone the draft says exactly what the site shows, so
 * it is not kept — the same rule a save follows (`HasDraft::matchesLive()`), which a prune written
 * straight to the columns would otherwise skip and leave the page «modified» with nothing waiting.
 *
 * @phpstan-type Dropped array{key: ?string, type: string, fields: list<string>}
 */
final readonly class StrayReport
{
    /**
     * @param  list<Dropped>  $site
     * @param  list<Dropped>  $draft
     */
    public function __construct(
        public array $site = [],
        public array $draft = [],
        public bool $draftDropped = false,
    ) {}

    public function isEmpty(): bool
    {
        return $this->site === [] && $this->draft === [];
    }

    /**
     * Every block with stray values, with where it was: `site` or `draft`.
     *
     * @return list<array{where: string, key: ?string, type: string, fields: list<string>}>
     */
    public function rows(): array
    {
        return [
            ...array_map(static fn (array $one): array => ['where' => 'site'] + $one, $this->site),
            ...array_map(static fn (array $one): array => ['where' => 'draft'] + $one, $this->draft),
        ];
    }
}
