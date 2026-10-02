<?php

declare(strict_types=1);

namespace WebxUi\Seo\Mcp;

use WebxUi\Mcp\Tool;
use WebxUi\Seo\Links\LinkBlocks;
use WebxUi\Seo\Links\LinkImport;
use WebxUi\Seo\Links\LinkWriter;
use WebxUi\Seo\Models\SeoLinkBlock;
use WebxUi\Seo\Targets\ForeignHost;
use WebxUi\Seo\Targets\UrlTargets;

/**
 * Interlinking for an agent (§18.4) — handed out only while `webx-seo.links.enabled` is on.
 *
 * `links_import` is the one that earns its keep: the brief arrives as a table of hundreds of
 * donors, and an agent that reads it can send it in one call, preview first.
 */
final class LinkTools
{
    /**
     * @return list<Tool>
     */
    public static function all(): array
    {
        $link = [
            'type' => 'object',
            'properties' => [
                'acceptor' => ['type' => 'string', 'description' => 'The address the link leads to'],
                'anchor' => ['type' => 'string', 'description' => 'The words the link is written with'],
            ],
            'required' => ['acceptor', 'anchor'],
        ];

        return [
            Tool::read(
                'links_list',
                'Pages that carry an interlinking block: the donor address, its heading, how many links and how many of them are broken.',
                static fn (array $arguments): array => self::list($arguments),
                [
                    'properties' => [
                        'q' => ['type' => 'string', 'description' => 'Part of a donor address or of an anchor'],
                        'broken' => ['type' => 'boolean', 'description' => 'Only donors with broken links'],
                        'page' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
                scope: 'seo:read',
            ),

            Tool::read(
                'links_get',
                'The interlinking block of one page, by its address: heading and links in order, each with the address it leads to now and whether it is broken.',
                static function (array $arguments): array {
                    $block = self::block((string) ($arguments['url'] ?? ''));

                    return $block instanceof SeoLinkBlock
                        ? ['ok' => true, 'block' => app(LinkBlocks::class)->describe($block->load('items'))]
                        : ['ok' => false, 'reason' => 'That page has no interlinking block.'];
                },
                [
                    'properties' => ['url' => ['type' => 'string', 'description' => 'The donor address']],
                    'required' => ['url'],
                ],
                scope: 'seo:read',
            ),

            Tool::mutating(
                'links_set',
                'Write the whole interlinking block of one page: its links replace the ones it had. An address that redirects is replaced by its target and reported; a link to the page itself, a repeated link and an address on another site are refused.',
                static fn (array $arguments): array => self::set($arguments),
                [
                    'properties' => [
                        'url' => ['type' => 'string', 'description' => 'The donor address'],
                        'heading' => ['type' => 'string', 'description' => 'Leave empty for the site-wide default heading'],
                        'is_active' => ['type' => 'boolean'],
                        'links' => ['type' => 'array', 'items' => $link],
                    ],
                    'required' => ['url', 'links'],
                ],
                scope: 'seo:write',
            ),

            Tool::mutating(
                'links_delete',
                'Remove the interlinking block of one page.',
                static function (array $arguments): array {
                    $block = self::block((string) ($arguments['url'] ?? ''));

                    if (! $block instanceof SeoLinkBlock) {
                        return ['ok' => false, 'reason' => 'That page has no interlinking block.'];
                    }

                    if (! ($arguments['dry_run'] ?? false)) {
                        $block->delete();
                    }

                    return ['ok' => true, 'applied' => ! ($arguments['dry_run'] ?? false), 'id' => $block->id];
                },
                [
                    'properties' => ['url' => ['type' => 'string', 'description' => 'The donor address']],
                    'required' => ['url'],
                ],
                scope: 'seo:write',
            ),

            Tool::mutating(
                'links_import',
                'Apply an interlinking brief: one row per link — donor, acceptor, anchor, optional heading (taken from the first row of a donor that has one). Rows are grouped by donor in order. "replace" (default) replaces the blocks of the donors in the rows, "append" adds to them. Run with dry_run first: it reports donors, links, what is created and replaced, and the problems by row number.',
                static fn (array $arguments): array => app(LinkImport::class)->run(
                    self::rows($arguments['rows'] ?? null),
                    ($arguments['mode'] ?? null) === LinkImport::APPEND ? LinkImport::APPEND : LinkImport::REPLACE,
                    (bool) ($arguments['dry_run'] ?? false),
                ),
                [
                    'properties' => [
                        'rows' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'donor' => ['type' => 'string'],
                                    'acceptor' => ['type' => 'string'],
                                    'anchor' => ['type' => 'string'],
                                    'heading' => ['type' => 'string'],
                                ],
                                'required' => ['donor', 'acceptor', 'anchor'],
                            ],
                        ],
                        'mode' => ['type' => 'string', 'enum' => [LinkImport::REPLACE, LinkImport::APPEND]],
                    ],
                    'required' => ['rows'],
                ],
                scope: 'seo:write',
            ),

            Tool::mutating(
                'links_heading',
                'Set one heading on many donors at once: every donor whose address is the prefix or below it, or the donors given by id. An empty heading means the site-wide default. dry_run reports how many and which.',
                static fn (array $arguments): array => self::heading($arguments),
                [
                    'properties' => [
                        'prefix' => ['type' => 'string', 'description' => 'An address prefix, e.g. /catalog/appliances/'],
                        'ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'heading' => ['type' => 'string'],
                    ],
                ],
                scope: 'seo:write',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function list(array $arguments): array
    {
        $blocks = app(LinkBlocks::class);
        $found = $blocks->search(is_string($arguments['q'] ?? null) ? $arguments['q'] : null, (bool) ($arguments['broken'] ?? false));
        $page = max(1, (int) ($arguments['page'] ?? 1));

        return [
            'total' => $found->count(),
            'page' => $page,
            'pages' => max(1, (int) ceil($found->count() / 50)),
            'blocks' => $found->forPage($page, 50)->map(static fn (SeoLinkBlock $block): array => $blocks->describe($block, false))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function set(array $arguments): array
    {
        $writer = app(LinkWriter::class);
        $links = is_array($arguments['links'] ?? null) ? array_values(array_filter($arguments['links'], 'is_array')) : [];
        $plan = $writer->plan((string) ($arguments['url'] ?? ''), $links);

        if ($plan->donor === null || $plan->hasErrors()) {
            return ['ok' => false, 'reason' => 'Some links were refused; nothing was written.', 'problems' => $plan->problems];
        }

        $attributes = ['heading' => is_string($arguments['heading'] ?? null) ? $arguments['heading'] : null];

        if (isset($arguments['is_active'])) {
            $attributes['is_active'] = (bool) $arguments['is_active'];
        }

        if ($arguments['dry_run'] ?? false) {
            return ['ok' => true, 'applied' => false, 'links' => count($plan->items), 'problems' => $plan->problems];
        }

        $block = $writer->save($plan, $attributes);

        return ['ok' => true, 'applied' => true, 'id' => $block->id, 'links' => count($plan->items), 'problems' => $plan->problems];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function heading(array $arguments): array
    {
        $ids = is_array($arguments['ids'] ?? null) ? array_values(array_map('intval', $arguments['ids'])) : null;
        $prefix = is_string($arguments['prefix'] ?? null) ? $arguments['prefix'] : null;

        if (($ids === null || $ids === []) && ($prefix === null || trim($prefix) === '')) {
            return ['ok' => false, 'reason' => 'Name the donors: an address prefix or ids.'];
        }

        $blocks = app(LinkBlocks::class);

        return $blocks->applyHeading(
            $blocks->selection($ids, $prefix),
            is_string($arguments['heading'] ?? null) ? $arguments['heading'] : null,
            (bool) ($arguments['dry_run'] ?? false),
        );
    }

    private static function block(string $url): ?SeoLinkBlock
    {
        try {
            $target = app(UrlTargets::class)->resolve($url)->target;
        } catch (ForeignHost) {
            return null;
        }

        return app(LinkWriter::class)->blockFor($target);
    }

    /**
     * Numbered from one, the way a person counts the rows they sent.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function rows(mixed $rows): array
    {
        $numbered = [];

        foreach (is_array($rows) ? array_values($rows) : [] as $index => $row) {
            if (is_array($row)) {
                $numbered[$index + 1] = $row;
            }
        }

        return $numbered;
    }
}
