<?php

declare(strict_types=1);

namespace WebxUi\Press\Panel;

use JsonException;
use WebxUi\Press\Support\Kinds;

/**
 * The description of `press.outlet-form`, with what only the site's config knows put into it
 * (§4.6): the kinds an article may have, as the options of its select — which is also what the
 * select is validated against — and no slug where outlets have no pages.
 */
final class OutletScreen
{
    /** The node the kinds go into. */
    private const KIND = 'article-kind';

    /** The node that goes when outlets have no pages. */
    private const SLUG = 'slug';

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public static function build(string $path, bool $pages): array
    {
        /** @var array<string, mixed> $screen */
        $screen = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        /** @var list<array<string, mixed>> $root */
        $root = $screen['root'];
        $screen['root'] = self::walk($root, $pages);

        return $screen;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function walk(array $nodes, bool $pages): array
    {
        $kept = [];

        foreach ($nodes as $node) {
            if (! $pages && ($node['id'] ?? null) === self::SLUG) {
                continue;
            }

            if (($node['id'] ?? null) === self::KIND) {
                $node['props'] = [...(is_array($node['props'] ?? null) ? $node['props'] : []), 'options' => Kinds::options()];
            }

            if (is_array($node['children'] ?? null)) {
                /** @var list<array<string, mixed>> $children */
                $children = array_values($node['children']);
                $node['children'] = self::walk($children, $pages);
            }

            $kept[] = $node;
        }

        return $kept;
    }
}
