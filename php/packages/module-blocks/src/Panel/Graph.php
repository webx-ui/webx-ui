<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;

/**
 * Who calls whom (§3.5 of the components spec), from the `uses` of the versions.
 *
 * Only published versions count as callers: a parent's draft breaks nobody until it is
 * published, and it is checked then. Calls from view files are not here at all — they are not
 * in the tables — which is what a declared place and a `fallback` are for.
 *
 * Read whole rather than asked per type with a JSON query: a site has a few dozen types, the
 * section asks about all of them at once, and a JSON containment query is spelled differently on
 * every database this has to run on.
 */
final class Graph
{
    /** @var array<string, array{block: Block, calls: list<string>}>|null slug → a published type and what it calls */
    private ?array $published = null;

    /**
     * The published types that call each type, by the called type's slug.
     *
     * @return array<string, list<array{id: int, slug: string, title: string}>>
     */
    public function parents(): array
    {
        $parents = [];

        foreach ($this->published() as $slug => $row) {
            foreach ($row['calls'] as $called) {
                if ($called !== $slug) {
                    $parents[$called][] = ['id' => $row['block']->id, 'slug' => $slug, 'title' => $row['block']->title];
                }
            }
        }

        return $parents;
    }

    /**
     * @return list<array{id: int, slug: string, title: string}>
     */
    public function usedBy(string $slug): array
    {
        return $this->parents()[$slug] ?? [];
    }

    /**
     * Every published type that prints this one, directly or through others — the ones a change
     * to it can break. Nearest first.
     *
     * @return list<Block>
     */
    public function ancestors(string $slug): array
    {
        $parents = $this->parents();
        $published = $this->published();
        $seen = [$slug => true];
        $queue = [$slug];
        $found = [];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($parents[$current] ?? [] as $parent) {
                if (isset($seen[$parent['slug']])) {
                    continue;
                }

                $seen[$parent['slug']] = true;
                $queue[] = $parent['slug'];
                $found[] = $published[$parent['slug']]['block'];
            }
        }

        return $found;
    }

    /**
     * The circle a type would close if it called `$calls`: `['card', 'badge', 'card']`, or null.
     * From the calls given (the draft's) and on through what each called type publishes.
     *
     * @param  list<string>  $calls
     * @return list<string>|null
     */
    public function cycle(string $slug, array $calls): ?array
    {
        $published = $this->published();
        $edges = [$slug => $calls];

        foreach ($published as $other => $row) {
            if ($other !== $slug) {
                $edges[$other] = $row['calls'];
            }
        }

        return self::findCycle($edges, $slug);
    }

    /**
     * An order in which every node comes after what it calls — what an import publishes in, so
     * that a parent is checked against a child that is already there. Throws with the circle if
     * there is one.
     *
     * @param  array<string, list<string>>  $edges  Node → what it calls; calls outside the set are ignored.
     * @return list<string>
     *
     * @throws CallCycle
     */
    public static function order(array $edges): array
    {
        $order = [];
        $state = [];

        $visit = static function (string $node, array $path) use (&$visit, &$order, &$state, $edges): void {
            if (($state[$node] ?? null) === 'done') {
                return;
            }

            if (($state[$node] ?? null) === 'open') {
                $start = (int) array_search($node, $path, true);

                throw new CallCycle([...array_slice($path, $start), $node]);
            }

            $state[$node] = 'open';

            foreach ($edges[$node] ?? [] as $called) {
                if (isset($edges[$called])) {
                    $visit($called, [...$path, $node]);
                }
            }

            $state[$node] = 'done';
            $order[] = $node;
        };

        $nodes = array_keys($edges);
        sort($nodes);

        foreach ($nodes as $node) {
            $visit((string) $node, []);
        }

        return $order;
    }

    /**
     * @param  array<string, list<string>>  $edges
     * @return list<string>|null
     */
    private static function findCycle(array $edges, string $from): ?array
    {
        $seen = [];
        $walk = static function (string $node, array $path) use (&$walk, &$seen, $edges, $from): ?array {
            foreach ($edges[$node] ?? [] as $called) {
                if ($called === $from) {
                    return [...$path, $node, $from];
                }

                if (isset($seen[$called]) || ! isset($edges[$called])) {
                    continue;
                }

                $seen[$called] = true;
                $found = $walk($called, [...$path, $node]);

                if ($found !== null) {
                    return $found;
                }
            }

            return null;
        };

        return $walk($from, []);
    }

    /**
     * @return array<string, array{block: Block, calls: list<string>}>
     */
    private function published(): array
    {
        if ($this->published !== null) {
            return $this->published;
        }

        $rows = [];

        /** @var Block $block */
        foreach (Block::query()->published()->with('publishedVersion')->orderBy('sort')->orderBy('slug')->get() as $block) {
            $version = $block->publishedVersion;

            if ($version instanceof BlockVersion) {
                $rows[$block->slug] = ['block' => $block, 'calls' => $version->calls()];
            }
        }

        return $this->published = $rows;
    }
}
