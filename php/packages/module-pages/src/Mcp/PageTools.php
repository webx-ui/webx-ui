<?php

declare(strict_types=1);

namespace WebxUi\Pages\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Pages\Exceptions\PagesException;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\Editors;
use WebxUi\Pages\Panel\PageForm;
use WebxUi\Pages\Panel\Placement;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;

/**
 * What an agent can do with the pages of a site (§13).
 *
 * The same doors the panel uses: `PageForm` decides what a page's values are and checks them
 * against the described screen, `Placement` decides where a page may go, the model refuses what
 * the model always refuses. A page an agent wrote is a page the panel would have accepted, with
 * `mcp` in its history beside the name of whoever the token belongs to.
 *
 * One thing is deliberately missing and stays missing: none of these tools writes the content
 * of a page. Blocks are `blocks_edit_content`, which names the node it is changing and leaves
 * everything else alone (§13.1) — a second way of doing the same thing would be the one that
 * overwrites twenty blocks to fix a heading.
 */
final class PageTools
{
    /** Well past a real site's catalogue, and a stop for the one that is not. */
    private const LIMIT = 500;

    /** As many matches as anybody reads before narrowing the search, as in the panel. */
    private const SEARCH_LIMIT = 50;

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $page = [
            'type' => ['integer', 'string'],
            'description' => 'The page: its id, or its address — "/about", or "/" for the home page.',
        ];
        $locale = [
            'type' => 'string',
            'description' => 'A language code; the site\'s default when omitted. Text fields always answer with every language.',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string, or every language as { "en": "…", "ru": "…" }.',
        ];

        return [
            Tool::read(
                'tree',
                'The pages of this site: where each sits, what it is called in every language, the address it '
                .'answers at, whether it is on the site, and who touched it last. The whole tree from the home '
                .'page by default; with parent and depth, one level of it; with search, the pages whose name or '
                .'address contains a word; with trashed, what is in the bin. Read this first — a page is '
                .'identified by its address, and this is where the addresses are.',
                fn (array $arguments): array => $this->tree($arguments),
                ['properties' => [
                    'parent' => ['type' => ['integer', 'string'], 'description' => 'Start from this page instead of the home page.'],
                    'depth' => ['type' => 'integer', 'description' => 'How many levels below the starting page; 1 is that page and its children. All of them when omitted.'],
                    'search' => ['type' => 'string', 'description' => 'Pages whose title or address contains this. A flat list of matches, wherever they are; with trashed, the matches in the bin.'],
                    'status' => ['type' => 'string', 'enum' => [Page::STATUS_DRAFT, Page::STATUS_PUBLISHED, Page::STATUS_MODIFIED], 'description' => 'Never published · on the site · on the site with edits waiting.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What was deleted, newest first. A page that went down with a branch is restored with it and is not listed on its own.'],
                    'locale' => $locale,
                ]],
            ),

            Tool::read(
                'get',
                'One page in full: where it is, its trail of ancestors, the values of its editor — title and '
                .'address in every language, the SEO card, the tree of blocks — the revision those values are, '
                .'and a link to the draft as the site would print it. Pass blocks: false when you want the '
                .'settings and not twenty blocks; blocks_get_content with outline is the cheap way to read the '
                .'content itself.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->get($arguments, $user),
                ['properties' => [
                    'page' => $page,
                    'blocks' => ['type' => 'boolean', 'description' => 'The block tree in the values; true when omitted.'],
                ], 'required' => ['page']],
            ),

            Tool::mutating(
                'create',
                'Start a page under another one — under the home page when no parent is named. It is a draft: '
                .'nothing is on the site until somebody publishes it. The address is made from the title when '
                .'you do not write one, and an address another page already holds is refused rather than '
                .'quietly given a suffix.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    'title' => $text,
                    'slug' => $text + ['description' => 'The last segment of the address, per language; made from the title when omitted. The home page has none.'],
                    'parent' => ['type' => ['integer', 'string'], 'description' => 'The page it goes under; the home page when omitted.'],
                    'values' => ['type' => 'object', 'description' => 'The rest of the editor\'s fields — the SEO card, say — as pages_get returns them.'],
                ], 'required' => ['title']],
            ),

            Tool::mutating(
                'update',
                'Change the values of a page — title, address, the SEO card — into its draft. A field left out '
                .'keeps what it had. Send the revision pages_get gave you and the write is refused if somebody '
                .'saved in between, instead of quietly overwriting them. Content is not written here: blocks go '
                .'through blocks_edit_content.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'page' => $page,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as pages_get returns them. Localized fields take { "en": "…" }.'],
                    'revision' => ['type' => 'string', 'description' => 'The revision pages_get returned. Left out, the write goes in over whatever happened since.'],
                ], 'required' => ['page', 'values']],
            ),

            Tool::mutating(
                'move',
                'Put a page somewhere else in the tree: inside another page, or before or after it. The address '
                .'is the tree, so this rewrites the address of the page and of everything under it and leaves a '
                .'redirect on each of the old ones — the answer says how many. The home page does not move, and '
                .'nothing moves into its own branch.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->move($arguments)),
                ['properties' => [
                    'page' => $page,
                    'target' => ['type' => ['integer', 'string'], 'description' => 'The page it moves relative to.'],
                    'zone' => ['type' => 'string', 'enum' => Placement::ZONES, 'description' => 'inside makes it a child of the target; before and after make it a sibling.'],
                ], 'required' => ['page', 'target', 'zone']],
            ),

            Tool::mutating(
                'publish',
                'Put the draft on the site: its values become the page, the date is stamped and a version is '
                .'written. Ask a person first unless they asked you to publish.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->publish($arguments, $user)),
                ['properties' => ['page' => $page], 'required' => ['page']],
            ),

            Tool::mutating(
                'unpublish',
                'Take a page off the site. It answers 404 from then on; its address stays reserved and whatever '
                .'was being prepared is still being prepared.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->unpublish($arguments)),
                ['properties' => ['page' => $page], 'required' => ['page']],
            ),

            Tool::mutating(
                'delete',
                'Put a page in the bin together with everything under it — the whole branch goes, and its '
                .'addresses are released. The answer says how many pages that was. Nothing is destroyed: '
                .'pages_restore brings the branch back as long as nobody has taken its addresses.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['page' => $page], 'required' => ['page']],
            ),

            Tool::mutating(
                'restore',
                'Take a page out of the bin, with whatever went in with it. Refused if its address has been '
                .'given to another page in the meantime — which is the honest answer, not a page quietly '
                .'restored somewhere else. By id: a page in the bin has no address to name it by.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->restore($arguments)),
                ['properties' => [
                    'page' => ['type' => 'integer', 'description' => 'The id, as pages_tree with trashed reports it.'],
                ], 'required' => ['page']],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function tree(array $arguments): array
    {
        $locale = $this->locale($arguments);
        $search = trim((string) ($arguments['search'] ?? ''));

        $pages = match (true) {
            ($arguments['trashed'] ?? false) === true => $this->bin($search),
            $search !== '' => $this->matches($search, $arguments),
            default => $this->branch($arguments),
        };

        $editors = Editors::of($pages);

        return [
            'locale' => $locale,
            'locales' => $this->locales()->codes(),
            'count' => $pages->count(),
            'pages' => $pages->map(fn (Page $page): array => $this->summary($page, $editors))->values()->all(),
        ];
    }

    /**
     * The tree, or a part of it, in the order it reads top to bottom.
     *
     * Flat rather than nested, with `depth` and `parent_id` on every row: a list an agent can
     * read in one pass and rebuild into a tree if it wants one, and no arbitrary limit on how
     * deep the nesting in the answer may go.
     *
     * @param  array<string, mixed>  $arguments
     * @return Collection<int, Page>
     */
    private function branch(array $arguments): Collection
    {
        $start = isset($arguments['parent']) ? $this->page($arguments['parent']) : $this->home();
        $depth = $arguments['depth'] ?? null;

        /** @var Collection<int, Page> $pages */
        $pages = $this->listing($arguments)
            ->where('lft', '>=', $start->getLft())
            ->where('rgt', '<=', $start->getRgt())
            ->when(is_int($depth), fn (Builder $query): Builder => $query->where('depth', '<=', $start->getDepth() + (int) $depth))
            ->orderBy('lft')
            ->limit(self::LIMIT)
            ->get();

        return $pages;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return Collection<int, Page>
     */
    private function matches(string $term, array $arguments): Collection
    {
        /** @var Collection<int, Page> $found */
        $found = $this->searching($this->listing($arguments), $term)
            ->orderBy('lft')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        return $found;
    }

    /**
     * Narrow a query to pages whose name or address contains the term, in any language the
     * site has. An empty term narrows nothing, so the bin and the tree can both hand their
     * query through it.
     *
     * The `locale` argument says which language to answer in, not which one to look in: a page
     * an agent can see in the tree is a page it can find by name.
     *
     * @param  Builder<Page>  $query
     * @return Builder<Page>
     */
    private function searching(Builder $query, string $term): Builder
    {
        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(static function (Builder $nested) use ($like): void {
            $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
        });
    }

    /**
     * @return Collection<int, Page>
     */
    private function bin(string $term): Collection
    {
        $query = Page::onlyTrashed()->whereNull('trashed_with')->with('routes');

        /** @var Collection<int, Page> $trashed */
        $trashed = $this->searching($query, $term)
            ->orderByDesc('deleted_at')
            ->get();

        return $trashed;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return Builder<Page>
     */
    private function listing(array $arguments): Builder
    {
        $query = Page::query()->with('routes')->withCount('children');
        $status = (string) ($arguments['status'] ?? '');

        return match ($status) {
            Page::STATUS_DRAFT => $query->whereNull('published_at'),
            Page::STATUS_PUBLISHED => $query->whereNotNull('published_at')->whereNull('draft'),
            Page::STATUS_MODIFIED => $query->whereNotNull('published_at')->whereNotNull('draft'),
            default => $query,
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments, ?Authenticatable $user = null): array
    {
        $page = $this->page($arguments['page'] ?? null);
        $form = $this->form();

        $page->loadMissing('routes')->loadCount('children');

        $values = $form->values($page);

        if (($arguments['blocks'] ?? true) !== true) {
            unset($values['blocks']);
        }

        $ancestors = $page->pathFromRoot()->filter(static fn (Page $node): bool => ! $node->is($page));
        $editors = Editors::of([$page, ...$ancestors->all()]);

        return [
            'page' => $this->summary($page, $editors),
            'ancestors' => $ancestors
                ->map(fn (Page $node): array => $this->summary($node->loadMissing('routes'), $editors))
                ->values()
                ->all(),
            'values' => $values,
            // The page as you read it: send it back with pages_update and a write that would
            // land on top of somebody else's is refused instead.
            'revision' => $form->revision($page),
            'preview_url' => $this->preview($page, $user),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $parent = isset($arguments['parent']) ? $this->page($arguments['parent']) : $this->home();
        $title = $this->text($arguments['title'] ?? null, 'title');
        $slug = isset($arguments['slug']) ? $this->text($arguments['slug'], 'slug') : $this->slugFrom($title);
        $values = $arguments['values'] ?? [];

        if (! is_array($values)) {
            throw new ToolFailure('`values` must be an object of field name → value.');
        }

        $this->refuseBlocks($values);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_create' => ['title' => $title, 'slug' => $slug],
                'under' => $this->address($parent),
            ];
        }

        $page = new Page;
        $page->setTranslations('title', $title);
        $page->setTranslations('slug', $slug);
        $page->appendTo($parent);

        if ($values !== []) {
            $this->write($page, $values, $user);
        }

        return $this->get(['page' => $page->refresh()->getKey()], $user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $page = $this->page($arguments['page'] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value. pages_get says what the fields are.');
        }

        $this->refuseBlocks($values);
        $this->sameRevision($arguments, $page);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_write' => 'draft',
                'fields' => array_keys($values),
                'page' => $this->address($page),
            ];
        }

        $this->write($page, $values, $user);

        return $this->get(['page' => $page->refresh()->getKey()], $user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function move(array $arguments): array
    {
        $page = $this->page($arguments['page'] ?? null);
        $target = $this->page($arguments['target'] ?? null);
        $zone = (string) ($arguments['zone'] ?? '');

        if (! in_array($zone, Placement::ZONES, true)) {
            throw new ToolFailure('`zone` is one of '.implode(', ', Placement::ZONES).'.');
        }

        if ($this->dryRun($arguments)) {
            Placement::assert($page, $target, $zone);

            return [
                'dry_run' => true,
                'would_move' => $this->address($page),
                'zone' => $zone,
                'target' => $this->address($target),
                'addresses_would_change' => $page->descendants()->count() + 1,
            ];
        }

        $changed = Placement::apply($page, $target, $zone);

        return [
            'page' => $this->summary($page->loadMissing('routes')->loadCount('children'), Editors::of([$page])),
            // Every one of them is at a different address than it was a moment ago, and the
            // old ones are now redirects.
            'addresses_changed' => $changed,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function publish(array $arguments, ?Authenticatable $user): array
    {
        $page = $this->page($arguments['page'] ?? null);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_publish' => $this->address($page),
                'status' => $page->status(),
                'has_waiting_edits' => $page->hasDraft(),
            ];
        }

        $page->publish($this->authorId($user), EntityVersion::SOURCE_MCP);

        return ['page' => $this->summary($page->refresh()->loadMissing('routes')->loadCount('children'), Editors::of([$page]))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function unpublish(array $arguments): array
    {
        $page = $this->page($arguments['page'] ?? null);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_unpublish' => $this->address($page), 'status' => $page->status()];
        }

        $page->unpublish();

        return ['page' => $this->summary($page->refresh()->loadMissing('routes')->loadCount('children'), Editors::of([$page]))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $page = $this->page($arguments['page'] ?? null);
        $count = $page->descendants()->count() + 1;

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $count, 'page' => $this->address($page)];
        }

        $page->delete();

        return ['trashed' => $count, 'id' => $page->getKey()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function restore(array $arguments): array
    {
        $id = $arguments['page'] ?? null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            throw new ToolFailure('`page` must be the id of a page in the bin: an address names only a live page.');
        }

        $trashed = Page::withTrashed()->find((int) $id);

        if (! $trashed instanceof Page) {
            throw new ToolFailure("No page has the id [{$id}].");
        }

        if (! $trashed->trashed()) {
            throw new ToolFailure("Page [{$id}] is not in the bin.");
        }

        $count = $trashed->trashedBranch()->count() + 1;

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_restore' => $count, 'id' => $trashed->getKey()];
        }

        $trashed->restoreBranch();

        return [
            'restored' => $count,
            'page' => $this->summary($trashed->refresh()->loadMissing('routes')->loadCount('children'), Editors::of([$trashed])),
        ];
    }

    /**
     * One page as an agent needs it.
     *
     * Every language at once rather than the one the panel happens to be open in: an agent that
     * asked for a page and got one title has no way of knowing whether the others exist, and a
     * site with two languages has pages translated into one of them.
     *
     * The title and the address come from the draft where there is one, the way the panel's
     * list shows them — what somebody is working on is what they will look for — while `urls`
     * is the registry's and is therefore what the site answers at right now.
     *
     * @param  array<int, string>  $editors
     * @return array<string, mixed>
     */
    private function summary(Page $page, array $editors = []): array
    {
        $shown = $page->hasDraft() ? $page->withDraft() : $page;
        $id = (int) $page->getKey();

        $summary = [
            'id' => $id,
            'parent_id' => $page->parent_id,
            'depth' => $page->getDepth(),
            'is_home' => $page->isRoot(),
            'title' => $shown->getTranslations('title'),
            'slug' => $shown->getTranslations('slug'),
            'urls' => $this->urls($page),
            'status' => $page->status(),
            'has_draft' => $page->hasDraft(),
            'published_at' => $page->published_at?->toAtomString(),
            'updated_at' => $page->updated_at?->toAtomString(),
            'edited_by' => $editors[$id] ?? null,
            'children' => (int) ($page->getAttribute('children_count') ?? 0),
            // Arithmetic on the bounds rather than a query — that is what a nested set is for —
            // and it is the size of what a delete would take.
            'descendants' => intdiv($page->getRgt() - $page->getLft() - 1, 2),
            'can' => $page->capabilities(),
        ];

        if ($page->trashed()) {
            $summary['deleted_at'] = $page->deleted_at?->toAtomString();
            $summary['trashed_with'] = $page->trashed_with;
        }

        return $summary;
    }

    /**
     * The addresses the registry holds for this page, per language.
     *
     * A language missing from the map is a language the page has no address in — because it or
     * something above it never got a slug there (§8) — and that is a real state rather than an
     * accident worth papering over.
     *
     * @return array<string, array{path: string, url: string}>
     */
    private function urls(Page $page): array
    {
        $urls = [];

        foreach ($page->loadMissing('routes')->routes as $route) {
            if ($route->kind === Route::CANONICAL) {
                $urls[$route->locale] = ['path' => '/'.$route->path, 'url' => $page->url($route->locale)];
            }
        }

        return $urls;
    }

    /** A page in one line, for an answer that is about what happened rather than about the page. */
    private function address(Page $page): string
    {
        $urls = $this->urls($page);
        $path = $urls[$this->locales()->defaultCode()] ?? reset($urls);

        return sprintf('#%s (%s)', $page->getKey(), is_array($path) ? $path['path'] : 'no address');
    }

    /**
     * Check the values against the described screen and put them in the draft — the same call
     * the panel's `PUT` makes, so a module that added a field to the screen has it here too.
     *
     * @param  array<string, mixed>  $values
     */
    private function write(Page $page, array $values, ?Authenticatable $user): void
    {
        $this->form()->save($page, $values, null, $this->authorId($user), EntityVersion::SOURCE_MCP);
    }

    /**
     * Run a change, and turn a refusal into something the agent can read.
     *
     * Both kinds reach here written to be shown: a `PagesException` is a rule about the tree,
     * and a `ValidationException` is either the screen refusing a value or the registry
     * refusing an address (`PathRejected` is one). Anything else is a fault rather than an
     * answer and goes out as one.
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        } catch (PagesException $refused) {
            throw new ToolFailure($refused->getMessage());
        }
    }

    /**
     * Content is not written through here (§13.1).
     *
     * Said rather than ignored: an agent that sent a block tree and got a cheerful answer would
     * think it had saved one, and what it actually did was nothing.
     *
     * @param  array<string, mixed>  $values
     */
    private function refuseBlocks(array $values): void
    {
        if (array_key_exists('blocks', $values)) {
            throw new ToolFailure(
                'The content of a page is not written here: use blocks_edit_content, which names the block it '
                .'changes and leaves the rest of the page alone. Read it first with blocks_get_content.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function sameRevision(array $arguments, Page $page): void
    {
        $sent = $arguments['revision'] ?? null;

        if (! is_string($sent) || $sent === '') {
            return;
        }

        $current = $this->form()->revision($page);

        if ($sent !== $current) {
            throw new ToolFailure(
                "The page changed since you read it: revision is [{$current}], you sent [{$sent}]. "
                .'Read it again with pages_get and redo the edit on what is there now.'
            );
        }
    }

    /**
     * A page by id or by address.
     *
     * The address is what a site is talked about in — "the about page", not "page 14" — and it
     * is what the sitemap resource hands over, so both spellings are accepted and `/` is the
     * home page.
     */
    private function page(mixed $reference): Page
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $page = Page::query()->with('routes')->withCount('children')->find((int) $reference);

            return $page instanceof Page
                ? $page
                : throw new ToolFailure("No page has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('`page` is required: an id, or an address like "/about".');
        }

        $path = UrlNormaliser::key($reference);

        if ($path === '') {
            return $this->home();
        }

        $route = Route::query()
            ->where('path', $path)
            ->where('kind', Route::CANONICAL)
            ->where('entity_type', (new Page)->getMorphClass())
            ->first();

        $page = $route === null ? null : Page::query()->with('routes')->withCount('children')->find($route->entity_id);

        return $page instanceof Page
            ? $page
            : throw new ToolFailure("No page answers at [/{$path}]. pages_tree lists the addresses; an unpublished page still has one.");
    }

    private function home(): Page
    {
        $home = Page::query()->roots()->with('routes')->withCount('children')->orderBy('lft')->first();

        return $home instanceof Page
            ? $home
            : throw new ToolFailure('This site has no home page, which means its tree has no root — run the migrations.');
    }

    /**
     * A text field as it arrives: one language or all of them.
     *
     * @return array<string, string>
     */
    private function text(mixed $value, string $field): array
    {
        if (is_string($value)) {
            return trim($value) === ''
                ? throw new ToolFailure("`{$field}` cannot be empty.")
                : [$this->locales()->defaultCode() => trim($value)];
        }

        if (! is_array($value) || $value === []) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if (! is_string($text) || trim($text) === '') {
                continue;
            }

            $texts[(string) $locale] = trim($text);
        }

        return $texts === [] ? throw new ToolFailure("`{$field}` cannot be empty.") : $texts;
    }

    /**
     * The address made out of the name, language by language.
     *
     * `Str::slug` transliterates, so a Russian title gives a Latin address rather than a
     * percent-encoded one — the same thing the panel's dialog does when the editor writes a
     * name and no address.
     *
     * @param  array<string, string>  $title
     * @return array<string, string>
     */
    private function slugFrom(array $title): array
    {
        return array_filter(array_map(static fn (string $text): string => Str::slug($text), $title));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function locale(array $arguments): string
    {
        $wanted = $arguments['locale'] ?? null;

        if (is_string($wanted) && $wanted !== '') {
            return $this->locales()->has($wanted)
                ? $wanted
                : throw new ToolFailure("This site is not published in [{$wanted}]. It has: ".implode(', ', $this->locales()->codes()).'.');
        }

        return $this->locales()->defaultCode();
    }

    private function preview(Page $page, ?Authenticatable $user): ?string
    {
        try {
            return Preview::url($page, $this->authorId($user));
        } catch (Throwable) {
            // A page the preview cannot sign — no address yet — is still a page worth reading.
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function authorId(?Authenticatable $user): ?int
    {
        $id = $user?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    private function form(): PageForm
    {
        return $this->container->make(PageForm::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }
}
