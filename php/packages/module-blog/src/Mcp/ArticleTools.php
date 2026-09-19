<?php

declare(strict_types=1);

namespace WebxUi\Blog\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Blog\Exceptions\BlogException;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Models\Tag;
use WebxUi\Blog\Panel\ArticleForm;
use WebxUi\Blog\Panel\ArticleList;
use WebxUi\Blog\Panel\Instant;
use WebxUi\Blog\Panel\Revision;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;

/**
 * What an agent can do with the articles of a blog (§13).
 *
 * The same doors the panel uses, which is the whole design rather than a convenience. The list
 * is {@see ArticleList} — the five states of §10 are one answer and not two — the values are
 * checked by {@see ArticleForm} against the described screen, so a tab `module-seo` put on the
 * editor is a field an agent can write, and the date goes through `publish()` because that is
 * where scheduling lives (§7). An article an agent wrote is one the panel would have accepted.
 *
 * One thing is deliberately missing and stays missing: none of these tools writes the body of an
 * article. Blocks are `blocks_edit_content`, which names the node it changes and leaves the rest
 * alone — a second way of doing the same thing would be the one that overwrites twenty blocks to
 * fix a heading.
 */
final class ArticleTools
{
    /** A page of the list, and a stop for an agent that asks for the whole blog. */
    private const PER_PAGE = 50;

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $article = [
            'type' => ['integer', 'string'],
            'description' => 'The article: its id, or the address it answers at — "/blog/how-to-choose".',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string, or every language as { "en": "…", "ru": "…" }.',
        ];

        return [
            Tool::read(
                'list',
                'The articles of this blog, newest first with the pinned ones above them: what each is called '
                .'in every language, the address it answers at, whether it is on the site, when it goes out, '
                .'who signed it, and which rubrics and tags it carries. Narrow it by state, by rubric, by tag, '
                .'by author or by a word in the title. Read this first — an article is named by its address, '
                .'and this is where the addresses are.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Articles whose title or address contains this, in any language the site has.'],
                    'status' => ['type' => 'string', 'enum' => [
                        Article::STATUS_DRAFT,
                        Article::STATUS_SCHEDULED,
                        Article::STATUS_PUBLISHED,
                        Article::STATUS_MODIFIED,
                        Article::STATUS_UNPUBLISHED,
                    ], 'description' => 'Never published · dated ahead and waiting · on the site · on the site with edits waiting · taken off it.'],
                    'rubric' => ['type' => ['integer', 'string'], 'description' => 'Only the articles in this rubric: its id or its slug. rubrics_list has both.'],
                    'tag' => ['type' => ['integer', 'string'], 'description' => 'Only the articles carrying this tag: its id or its slug.'],
                    'author' => ['type' => 'integer', 'description' => 'Only the articles signed by this administrator.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin, newest first. An article in the bin has no address.'],
                    'page' => ['type' => 'integer', 'description' => 'Which page of the list; 1 when omitted.'],
                    'per_page' => ['type' => 'integer', 'description' => 'How many on a page, at most '.self::PER_PAGE.'.'],
                ]],
            ),

            Tool::read(
                'get',
                'One article in full: the values of its editor — title, address and lead in every language, the '
                .'rubrics in the order that makes the first one the main one, the tags, the cover, the day it '
                .'goes out, the SEO card, the tree of blocks — the revision those values are, and a link to the '
                .'draft as the site would print it. Pass blocks: false when you want the settings and not '
                .'twenty blocks; blocks_get_content with outline is the cheap way to read the body itself.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->get($arguments, $user),
                ['properties' => [
                    'article' => $article,
                    'blocks' => ['type' => 'boolean', 'description' => 'The block tree in the values; true when omitted.'],
                ], 'required' => ['article']],
            ),

            Tool::mutating(
                'create',
                'Start an article. It is a draft: nothing is on the site and nothing is dated until somebody '
                .'publishes it. The address is made from the title when you do not write one, and one another '
                .'article — or a rubric, or a page — already holds is refused rather than quietly given a '
                .'suffix. Rubrics and tags can be set here, and both take effect at once: they are rows in a '
                .'pivot rather than values in a draft.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    'title' => $text,
                    'slug' => $text + ['description' => 'The last segment of the address, per language; made from the title when omitted.'],
                    'values' => ['type' => 'object', 'description' => 'The rest of the editor\'s fields — the lead, the rubrics, the tags, the SEO card — as articles_get returns them.'],
                ], 'required' => ['title']],
            ),

            Tool::mutating(
                'update',
                'Change the values of an article — title, address, lead, rubrics, tags, cover, the SEO card — '
                .'into its draft. A field left out keeps what it had. Send the revision articles_get gave you '
                .'and the write is refused if somebody saved in between, instead of quietly overwriting them. '
                .'The body is not written here: blocks go through blocks_edit_content. Rubrics, tags, the pin '
                .'and the related list are not drafted — they are on the site the moment they are saved.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'article' => $article,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as articles_get returns them. Localized fields take { "en": "…" }.'],
                    'revision' => ['type' => 'string', 'description' => 'The revision articles_get returned. Left out, the write goes in over whatever happened since.'],
                ], 'required' => ['article', 'values']],
            ),

            Tool::mutating(
                'publish',
                'Put the draft on the site under a date: its values become the article, a version is written, '
                .'and the date decides everything else. A day ahead means the article waits and answers 404 '
                .'until its morning; a day behind moves it down the feed. Omit `at` and it goes out now, or on '
                .'the day the editor already chose for it. Ask a person first unless they asked you to publish.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->publish($arguments, $user)),
                ['properties' => [
                    'article' => $article,
                    'at' => ['type' => 'string', 'description' => 'The day and hour it goes out, with an offset: "2026-10-01T09:00:00+03:00". Now, or the day already chosen, when omitted.'],
                ], 'required' => ['article']],
            ),

            Tool::mutating(
                'unpublish',
                'Take an article off the site. It answers 404 from then on and drops out of the feed; its '
                .'address stays reserved and whatever was being prepared is still being prepared.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->unpublish($arguments)),
                ['properties' => ['article' => $article], 'required' => ['article']],
            ),

            Tool::mutating(
                'delete',
                'Put an article in the bin. Its address is released — an article nobody can reach has no '
                .'business holding a spelling the next one called the same thing will want — so it can only be '
                .'named by its id afterwards. Nothing is destroyed: the bin in the panel puts it back, as long '
                .'as nobody has taken its address in the meantime.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['article' => $article], 'required' => ['article']],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $perPage = (int) ($arguments['per_page'] ?? self::PER_PAGE);
        $perPage = max(1, min(self::PER_PAGE, $perPage));

        // Through the panel's own query rather than a second one written here: the five states of
        // §10 are two columns and a subquery, and an agent told "draft" about an article that was
        // live this morning is being told the same lie the list refuses to tell.
        $page = $this->container->make(ArticleList::class)
            ->build($this->request($arguments))
            ->paginate($perPage, ['*'], 'page', max(1, (int) ($arguments['page'] ?? 1)));

        return [
            'locales' => $this->locales()->codes(),
            'default_locale' => $this->locales()->defaultCode(),
            'count' => $page->count(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'articles' => array_map(fn (Article $article): array => $this->summary($article), $this->items($page)),
        ];
    }

    /**
     * The arguments as the list reads them.
     *
     * A rubric and a tag are accepted by slug as well as by id, because that is what an agent has
     * in its hands: it read `blog/repairs` off an address or out of `rubrics_list`, and asking it
     * to carry a number around is asking it to make one up.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function request(array $arguments): Request
    {
        $query = [
            'q' => (string) ($arguments['search'] ?? ''),
            'status' => (string) ($arguments['status'] ?? ''),
            'trashed' => ($arguments['trashed'] ?? false) === true ? '1' : '0',
        ];

        $rubric = $this->keyOf(Rubric::query(), $arguments['rubric'] ?? null, 'rubric', 'rubrics_list');
        $tag = $this->keyOf(Tag::query(), $arguments['tag'] ?? null, 'tag', 'tags_list');

        if ($rubric !== null) {
            $query['rubric'] = (string) $rubric;
        }

        if ($tag !== null) {
            $query['tag'] = (string) $tag;
        }

        if (isset($arguments['author'])) {
            $query['author'] = (string) $arguments['author'];
        }

        return Request::create('/', 'GET', $query);
    }

    /**
     * A rubric or a tag by id or by slug, as a number the list can use.
     *
     * The slug is looked for in every language the site has rather than in the default one: an
     * agent reads a slug off an address, and the address it read may have been the Russian one.
     * `like` with nothing wildcarded in it is an exact match — the scope that searches every
     * language is the only one there is, and there is no reason to write a second.
     *
     * @param  Builder<Rubric>|Builder<Tag>  $query
     */
    private function keyOf(Builder $query, mixed $reference, string $what, string $tool): ?int
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            return (int) $reference;
        }

        if (! is_string($reference)) {
            throw new ToolFailure("`{$what}` is an id or a slug.");
        }

        $slug = trim($reference, '/');
        $found = $query->whereTranslationLikeAny('slug', $slug)->first();

        return $found === null
            ? throw new ToolFailure("No {$what} is slugged [{$slug}]. {$tool} says what there is.")
            : (int) $found->getKey();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments, ?Authenticatable $user = null): array
    {
        $article = $this->article($arguments['article'] ?? null);
        $values = $this->form()->values($article);

        if (($arguments['blocks'] ?? true) !== true) {
            unset($values['blocks']);
        }

        return [
            'article' => $this->summary($article),
            'values' => $values,
            // The article as you read it: send it back with articles_update and a write that
            // would land on top of somebody else's is refused instead.
            'revision' => Revision::of($article),
            'preview_url' => $this->preview($article, $user),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
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
                'would_answer_at' => $this->addresses($slug),
            ];
        }

        $article = new Article;
        $article->setTranslations('title', $title);
        $article->setTranslations('slug', $slug);
        $article->author_id = $this->authorId($user);
        $article->save();

        if ($values !== []) {
            $this->form()->save($article, $values, null, $this->authorId($user));
        }

        return $this->get(['article' => $article->refresh()->getKey()], $user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $article = $this->article($arguments['article'] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value. articles_get says what the fields are.');
        }

        $this->refuseBlocks($values);
        $this->sameRevision($arguments, $article);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_write' => 'draft',
                'fields' => array_keys($values),
                'article' => $this->reference($article),
            ];
        }

        $this->form()->save($article, $values, null, $this->authorId($user));

        return $this->get(['article' => $article->refresh()->getKey()], $user);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function publish(array $arguments, ?Authenticatable $user): array
    {
        $article = $this->article($arguments['article'] ?? null);
        $at = Instant::from($arguments['at'] ?? null);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_publish' => $this->reference($article),
                'status' => $article->status(),
                'has_waiting_edits' => $article->hasDraft(),
                // What "publish" would put on the column: the day asked for, the day the editor
                // already chose, or this moment.
                'would_go_out_at' => ($at ?? $this->planned($article))?->toAtomString(),
            ];
        }

        $article->publish($this->authorId($user), EntityVersion::SOURCE_MCP, at: $at);
        $article->refresh();

        return [
            'article' => $this->summary($article),
            // Said rather than left to be read off the status: an article dated ahead is
            // published as far as the frame underneath is concerned and is not on the site (§7).
            'on_the_site' => $article->isPublished(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function unpublish(array $arguments): array
    {
        $article = $this->article($arguments['article'] ?? null);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_unpublish' => $this->reference($article), 'status' => $article->status()];
        }

        $article->unpublish();

        return ['article' => $this->summary($article->refresh())];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $article = $this->article($arguments['article'] ?? null);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->reference($article), 'status' => $article->status()];
        }

        $article->delete();

        return ['trashed' => true, 'id' => (int) $article->getKey()];
    }

    /**
     * One article as an agent needs it.
     *
     * Every language at once rather than the one the panel happens to be open in: an agent that
     * asked for an article and got one title has no way of knowing whether the others exist, and
     * a blog with two languages has articles written in one of them.
     *
     * The title comes from the draft where there is one — what somebody is working on is what
     * they will look for — while `urls` is the registry's and is therefore what the site answers
     * at right now.
     *
     * @return array<string, mixed>
     */
    private function summary(Article $article): array
    {
        $shown = $article->hasDraft() ? $article->withDraft() : $article;

        $summary = [
            'id' => (int) $article->getKey(),
            'title' => $shown->getTranslations('title'),
            'slug' => $shown->getTranslations('slug'),
            'lead' => $shown->getTranslations('lead'),
            'urls' => $this->urls($article),
            'status' => $article->status(),
            'has_draft' => $article->hasDraft(),
            'pinned' => (bool) $article->pinned,
            // Null means nobody has decided when it goes out. Ahead of now means it is waiting.
            'published_at' => $article->published_at?->toAtomString(),
            'planned_at' => $article->published_at === null ? $this->planned($article)?->toAtomString() : null,
            'updated_at' => $article->updated_at?->toAtomString(),
            'author' => $article->author === null
                ? null
                : ['id' => (int) $article->author->getKey(), 'name' => (string) $article->author->name],
            // In the order that makes the first one the main one (§2.6): the breadcrumbs, "more
            // in this rubric" and `<category>` in the RSS all read it.
            'rubrics' => $article->rubrics
                ->map(static fn (Rubric $rubric): array => [
                    'id' => (int) $rubric->getKey(),
                    'title' => $rubric->getTranslations('title'),
                    'slug' => $rubric->getTranslations('slug'),
                ])
                ->values()
                ->all(),
            'tags' => $article->tags
                ->map(static fn (Tag $tag): array => [
                    'id' => (int) $tag->getKey(),
                    'title' => $tag->getTranslations('title'),
                    'slug' => $tag->getTranslations('slug'),
                ])
                ->values()
                ->all(),
        ];

        if ($article->trashed()) {
            $summary['deleted_at'] = $article->deleted_at?->toAtomString();
        }

        return $summary;
    }

    /**
     * The addresses the registry holds for this article, per language.
     *
     * A language missing from the map is a language the article has no address in — because it
     * names no slug there (§9) — and that is a real state rather than an accident worth papering
     * over.
     *
     * @return array<string, array{path: string, url: string}>
     */
    private function urls(Article $article): array
    {
        $urls = [];

        foreach ($article->loadMissing('routes')->routes as $route) {
            if ($route->kind === Route::CANONICAL) {
                $urls[$route->locale] = ['path' => '/'.$route->path, 'url' => $article->url($route->locale)];
            }
        }

        return $urls;
    }

    /**
     * An article in one line, for an answer that is about what happened rather than about it.
     */
    private function reference(Article $article): string
    {
        $urls = $this->urls($article);
        $address = $urls[$this->locales()->defaultCode()] ?? reset($urls);

        return sprintf('#%s (%s)', $article->getKey(), is_array($address) ? $address['path'] : 'no address');
    }

    /**
     * Where an article with these slugs would answer, before it exists — what a dry run reports.
     *
     * @param  array<string, string>  $slug
     * @return array<string, string>
     */
    private function addresses(array $slug): array
    {
        $prefix = UrlNormaliser::key((string) config('webx-blog.prefix', 'blog'));

        return array_map(
            static fn (string $one): string => '/'.UrlNormaliser::join($prefix, $one),
            $slug,
        );
    }

    /**
     * The day an article that has never been on the site is meant to go out.
     *
     * It waits in the draft, because `published_at` is what "on the site" means and there is no
     * column for a date that has not happened yet (§7).
     */
    private function planned(Article $article): ?Carbon
    {
        return Instant::from($article->draftValues()['published_at'] ?? null);
    }

    /**
     * Run a change, and turn a refusal into something the agent can read.
     *
     * Both kinds reach here written to be shown: a `BlogException` is a rule about the blog — a
     * rubric that still holds articles, a tag merged into itself — and a `ValidationException` is
     * either the screen refusing a value or the registry refusing an address. Anything else is a
     * fault rather than an answer and goes out as one.
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
        } catch (BlogException $refused) {
            throw new ToolFailure($refused->getMessage());
        }
    }

    /**
     * The body is not written through here (§13).
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
                'The body of an article is not written here: use blocks_edit_content, which names the block it '
                .'changes and leaves the rest of the article alone. Read it first with blocks_get_content.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function sameRevision(array $arguments, Article $article): void
    {
        $sent = $arguments['revision'] ?? null;

        if (! is_string($sent) || $sent === '') {
            return;
        }

        $current = Revision::of($article);

        if ($sent !== $current) {
            throw new ToolFailure(
                "The article changed since you read it: revision is [{$current}], you sent [{$sent}]. "
                .'Read it again with articles_get and redo the edit on what is there now.'
            );
        }
    }

    /**
     * An article by id or by address.
     *
     * The address is what a blog is talked about in — "the piece about belts", which is
     * `/blog/belts` — and it is what `blog://feed` hands over, so both spellings are accepted.
     */
    private function article(mixed $reference): Article
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $article = $this->loaded(Article::query()->find((int) $reference));

            return $article instanceof Article
                ? $article
                : throw new ToolFailure("No article has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('`article` is required: an id, or an address like "/blog/how-to-choose".');
        }

        $path = UrlNormaliser::key($reference);

        $route = Route::query()
            ->where('path', $path)
            ->where('kind', Route::CANONICAL)
            ->where('entity_type', (new Article)->getMorphClass())
            ->first();

        $article = $route === null ? null : $this->loaded(Article::query()->find($route->entity_id));

        return $article instanceof Article
            ? $article
            : throw new ToolFailure(
                "No article answers at [/{$path}]. articles_list has the addresses; an article that is not on "
                .'the site still has one, and one in the bin has none at all.'
            );
    }

    private function loaded(?Article $article): ?Article
    {
        return $article?->loadMissing(['routes', 'rubrics', 'tags', 'author', 'cover']);
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
     * The address made out of the title, language by language.
     *
     * `Str::slug` transliterates, so a Russian title gives a Latin address rather than a
     * percent-encoded one — the same thing the panel's dialog does when the editor writes a name
     * and no address.
     *
     * @param  array<string, string>  $title
     * @return array<string, string>
     */
    private function slugFrom(array $title): array
    {
        return array_filter(array_map(static fn (string $text): string => Str::slug($text), $title));
    }

    private function preview(Article $article, ?Authenticatable $user): ?string
    {
        try {
            return Preview::url($article, $this->authorId($user));
        } catch (Throwable) {
            // An article the preview cannot sign — no address yet — is still one worth reading.
            return null;
        }
    }

    /**
     * @param  LengthAwarePaginator<int, Article>  $page
     * @return list<Article>
     */
    private function items(LengthAwarePaginator $page): array
    {
        /** @var list<Article> $items */
        $items = array_values($page->items());

        return $items;
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

    private function form(): ArticleForm
    {
        return $this->container->make(ArticleForm::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }
}
