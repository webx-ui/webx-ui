<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/**
 * The sources of one link of the chain, in order: `priority` first (lower — earlier), then the
 * order they were registered in. That is how a rules module and a coupons module agree who goes
 * first without knowing each other.
 *
 * @template TSource of object
 */
abstract class Sources
{
    /** @var list<array{source: TSource, priority: int, order: int}> */
    private array $sources = [];

    /** @param  TSource  $source */
    protected function add(object $source, int $priority): void
    {
        $this->sources[] = ['source' => $source, 'priority' => $priority, 'order' => count($this->sources)];
    }

    /** @return list<TSource> */
    public function all(): array
    {
        return array_map(static fn (array $entry): object => $entry['source'], $this->describe());
    }

    /** @return list<array{source: TSource, priority: int}> in order, for a catalogue that lists them */
    public function describe(): array
    {
        $sources = $this->sources;
        usort($sources, static fn (array $a, array $b): int => [$a['priority'], $a['order']] <=> [$b['priority'], $b['order']]);

        return array_map(static fn (array $entry): array => ['source' => $entry['source'], 'priority' => $entry['priority']], $sources);
    }
}
