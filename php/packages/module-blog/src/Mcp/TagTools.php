<?php

declare(strict_types=1);

namespace WebxUi\Blog\Mcp;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use WebxUi\Blog\Exceptions\BlogException;
use WebxUi\Blog\Models\Tag;
use WebxUi\Blog\Panel\TagList;
use WebxUi\Blog\Support\TagMerge;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * The tags of the blog, and the one operation that puts them back together (§13).
 *
 * Two tools, and the pair is the point. Tags are made from the article form by the hundred, so
 * within six months the table holds "belts", "belt" and "drive belts" and all three are right —
 * which is exactly the kind of tidying an agent is good at and a person puts off. `tags_list`
 * sorts them by how much they are used, so the duplicates end up next to the word they are
 * duplicates of; `tags_merge` is the irreversible half, and it says so.
 *
 * What is deliberately not here is making a tag and renaming one. A tag is made by writing an
 * article — `articles_update` with a word the blog does not have yet is refused, and an agent
 * that wanted a new word should say so to a person — and renaming one is a decision about what
 * a word on the site means.
 */
final class TagTools
{
    /** As many as an agent reads before narrowing the search. */
    private const PER_PAGE = 50;

    /** A merge is read before it is run; past this it is a bulk job somebody should look at. */
    private const MERGE_LIMIT = 20;

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $tag = [
            'type' => ['integer', 'string'],
            'description' => 'A tag: its id, or its slug — "belts".',
        ];

        return [
            Tool::read(
                'list',
                'The tags of this blog, most used first: the word in every language, the address it answers at, '
                .'how many articles carry it, and whether its page is in the index. Narrow it to the ones '
                .'nothing is filed under, or to the ones search engines are told to leave alone. Sorted by use '
                .'because that is how duplicates show themselves — "belts" with forty articles and "belt" with '
                .'one is a merge waiting to happen.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Tags whose word or address contains this, in any language the site has.'],
                    'empty' => ['type' => 'boolean', 'description' => 'Only the tags nothing is filed under.'],
                    'noindex' => ['type' => 'boolean', 'description' => 'Only the tags whose page is out of the index.'],
                    'sort' => ['type' => 'string', 'enum' => [TagList::SORT_ARTICLES, TagList::SORT_NAME], 'description' => 'Most used first, or alphabetical — which is how "belt", "belts" and "belt drive" end up next to each other.'],
                    'page' => ['type' => 'integer', 'description' => 'Which page of the list; 1 when omitted.'],
                    'per_page' => ['type' => 'integer', 'description' => 'How many on a page, at most '.self::PER_PAGE.'.'],
                ]],
                permission: ['blog.articles.view', 'blog.articles.manage', 'blog.taxonomy.manage'],
            ),

            Tool::mutating(
                'merge',
                'Put several tags into one: every article filed under them comes out carrying the surviving '
                .'word, and the duplicates are deleted. This cannot be undone — the rows that say which article '
                .'carried which word are gone, and putting a tag back would not put them back. With redirect, '
                .'the addresses the merged tags answered at stay alive as permanent redirects onto the '
                .'survivor; without it they simply stop answering. Run it as a dry run first, and tell a person '
                .'what it would do before you do it.',
                fn (array $arguments): array => $this->merge($arguments),
                ['properties' => [
                    'keep' => $tag + ['description' => 'The tag that survives. Its word, its address and its place in the index are the ones that remain.'],
                    'merged' => [
                        'type' => 'array',
                        'items' => ['type' => ['integer', 'string']],
                        'description' => 'The tags that go, by id or by slug. At most '.self::MERGE_LIMIT.' at a time.',
                    ],
                    'redirect' => ['type' => 'boolean', 'description' => 'Leave a permanent redirect from each old address onto the survivor. False when omitted: a tag made by mistake this morning does not deserve one.'],
                ], 'required' => ['keep', 'merged']],
                permission: 'blog.taxonomy.manage',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $perPage = max(1, min(self::PER_PAGE, (int) ($arguments['per_page'] ?? self::PER_PAGE)));

        // Through the panel's own list, because the interesting column is not a `where` at all:
        // whether a tag is in the index is the answer of `module-seo` about a rule written for
        // its address (§12), and a second implementation of that would be a second answer.
        $page = $this->container->make(TagList::class)->page(Request::create('/', 'GET', [
            'q' => (string) ($arguments['search'] ?? ''),
            'empty' => ($arguments['empty'] ?? false) === true ? '1' : '0',
            'noindex' => ($arguments['noindex'] ?? false) === true ? '1' : '0',
            'sort' => (string) ($arguments['sort'] ?? ''),
            'page' => (string) max(1, (int) ($arguments['page'] ?? 1)),
            'per_page' => (string) $perPage,
        ]));

        return [
            'count' => count($page['data']),
            'total' => $page['meta']['total'] ?? 0,
            'page' => $page['meta']['current_page'] ?? 1,
            'last_page' => $page['meta']['last_page'] ?? 1,
            // Three answers and not two: `open` is the tag's own flag, `rule` is a rule in
            // `seo_urls` written for its address, `noindex` is out of the index (§12).
            'tags' => $page['data'],
            'totals' => $page['filters'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function merge(array $arguments): array
    {
        $keep = $this->tag($arguments['keep'] ?? null, 'keep');
        $merged = $this->tags($arguments['merged'] ?? null);
        $redirect = ($arguments['redirect'] ?? false) === true;

        $going = array_values(array_filter(
            $merged,
            static fn (Tag $tag): bool => $tag->getKey() !== $keep->getKey(),
        ));

        if ($going === []) {
            throw new ToolFailure('`merged` names nothing but the tag being kept. A merge has to have something to merge.');
        }

        if (($arguments[Tool::DRY_RUN] ?? false) === true) {
            return [
                'dry_run' => true,
                'would_keep' => $this->named($keep),
                'would_merge' => array_map(fn (Tag $tag): array => $this->named($tag), $going),
                'redirects_would_be_written' => $redirect,
                // Not the sum of the two: an article carrying both tags is one article, and a
                // number that counted it twice would be the number the answer is compared with.
                'articles_after' => $this->wouldCarry($keep, $going),
            ];
        }

        try {
            $carried = $this->container->make(TagMerge::class)->merge($going, $keep, $redirect);
        } catch (BlogException $refused) {
            throw new ToolFailure($refused->getMessage());
        }

        return [
            'kept' => $this->named($keep->refresh()),
            'merged' => count($going),
            'articles_count' => $carried,
            'redirects_written' => $redirect,
        ];
    }

    /**
     * How many articles the survivor would end up carrying: the union, counted once.
     *
     * @param  list<Tag>  $going
     */
    private function wouldCarry(Tag $keep, array $going): int
    {
        $ids = $keep->articles()->pluck('articles.id')->all();

        foreach ($going as $tag) {
            $ids = [...$ids, ...$tag->articles()->pluck('articles.id')->all()];
        }

        return count(array_unique($ids));
    }

    /**
     * @param  list<Tag>|null  $reference
     * @return list<Tag>
     */
    private function tags(mixed $reference): array
    {
        if (! is_array($reference) || $reference === []) {
            throw new ToolFailure('`merged` is a list of tags, by id or by slug.');
        }

        if (count($reference) > self::MERGE_LIMIT) {
            throw new ToolFailure(
                'At most '.self::MERGE_LIMIT.' tags in one merge. Past that it is a tidying job somebody should '
                .'look at before it runs, not one call.'
            );
        }

        return array_map(fn (mixed $one): Tag => $this->tag($one, 'merged'), array_values($reference));
    }

    private function tag(mixed $reference, string $field): Tag
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $tag = Tag::query()->find((int) $reference);

            return $tag instanceof Tag
                ? $tag
                : throw new ToolFailure("No tag has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure("`{$field}` names a tag: an id, or a slug like \"belts\".");
        }

        $slug = trim($reference, " \t\n\r\0\x0B/");
        $tag = Tag::query()->whereTranslationLikeAny('slug', $slug)->first();

        return $tag instanceof Tag
            ? $tag
            : throw new ToolFailure("No tag is slugged [{$slug}]. tags_list says what there is.");
    }

    /**
     * A tag in the shape an answer about a merge needs: which word, and how much of the blog it
     * was holding.
     *
     * @return array<string, mixed>
     */
    private function named(Tag $tag): array
    {
        return [
            'id' => (int) $tag->getKey(),
            'title' => $tag->getTranslations('title'),
            'slug' => $tag->getTranslations('slug'),
            'articles_count' => $tag->articles()->count(),
        ];
    }
}
