<?php

declare(strict_types=1);

namespace WebxUi\Pages\Panel;

use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Pages\Http\Resources\PageResource;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;
use WebxUi\Seo\Fields;

/**
 * The editor's screen on the server side: what its fields hold, what a save writes, and what
 * "the page as I read it" means.
 *
 * The screen is `pages.form` and the values are keyed by field name, so what a page is made of
 * is decided by the description rather than by this class — a module that adds a field to the
 * screen adds it here by doing nothing. What this class does know is which of those names are
 * columns of a page: `title`, `slug` and `blocks` go into the draft, and a field somebody else
 * put on the screen belongs to whoever put it there (§12 — the SEO card keeps its own values).
 */
final class PageForm
{
    public const SCREEN = 'pages.form';

    /**
     * The fields that are the page itself. In this order, so that the revision of one page is
     * the same string whichever way the values came in.
     *
     * @var list<string>
     */
    private const OWN = ['title', 'slug', 'blocks'];

    public function __construct(private readonly ScreenValues $values) {}

    /**
     * A page and everything its editor needs around it: the row, the trail above it for the
     * breadcrumbs, the values of the screen, the revision those values are, and a link to the
     * draft.
     *
     * The preview link is minted per response rather than kept on the page: it is signed and
     * short-lived, and an editor who has had the form open all morning would otherwise press
     * "preview" and get a 404 from a token that expired before lunch.
     *
     * @return array<string, mixed>
     */
    public function describe(Page $page, ?int $adminId = null): array
    {
        $ancestors = $page->pathFromRoot()->filter(static fn (Page $node): bool => ! $node->is($page));
        $editors = Editors::of([$page, ...$ancestors]);

        return [
            'page' => new PageResource($page, $editors),
            'ancestors' => $ancestors
                ->map(static fn (Page $node): PageResource => new PageResource($node, $editors))
                ->values()
                ->all(),
            'values' => $this->values($page),
            'revision' => $this->revision($page),
            'address_prefix' => $this->prefixes($ancestors->last()),
            'preview_url' => Preview::url($page, $adminId),
        ];
    }

    /**
     * The address of the page above, in every language it has one in.
     *
     * The form prints the whole address the page answers at and lets the field edit its last
     * segment, so it needs the rest of it — and per language, because a page has an address in
     * a language only where it and everything above it name one (§8). A prefix borrowed from
     * the language the panel happens to be open in would print an address the site does not
     * answer at; a language missing from this map is a language the page has no address in.
     *
     * @return array<string, string>
     */
    private function prefixes(mixed $parent): array
    {
        if (! $parent instanceof Page) {
            return [];
        }

        $prefixes = [];

        foreach ($parent->loadMissing('routes')->routes as $route) {
            if ($route->kind === Route::CANONICAL) {
                $prefixes[$route->locale] = $route->path;
            }
        }

        return $prefixes;
    }

    /**
     * What the form opens with: the draft laid over the columns, which is what the editor was
     * last working on rather than what the site is currently showing.
     *
     * `is_home` is not a field and is never written — it is there for the description to hide
     * the address with (§10): the home page's slug is empty in every language on purpose, and a
     * disabled empty field invites the one question it cannot answer.
     *
     * @return array<string, mixed>
     */
    public function values(Page $page): array
    {
        $shown = $page->hasDraft() ? $page->withDraft() : $page;

        return [
            'title' => $shown->getTranslations('title'),
            'slug' => $shown->getTranslations('slug'),
            'blocks' => $shown->blocksTree(),
            'is_home' => $page->isRoot(),
            // Never from the draft. What a page says about itself is saved when it is saved
            // (`HasSeo`), so the card shows what is on the site rather than what is waiting.
            Fields::SCREEN => $page->seoValue(),
        ];
    }

    /**
     * The page as a short string: the same content gives the same revision.
     *
     * A draft has no number of its own — autosaves are a ring and lose theirs — so "the page as
     * I read it" is the content itself, the way it is for a block tree (`module-blocks`, §18.1).
     * Two editors who saved the same thing did not conflict, and this says so.
     */
    public function revision(Page $page): string
    {
        $values = $this->values($page);
        $content = [];

        foreach (self::OWN as $field) {
            $content[$field] = $values[$field] ?? null;
        }

        return substr(sha1(json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), 0, 12);
    }

    /**
     * Check what came in against the screen and put it in the draft.
     *
     * The whole draft is replaced rather than merged — a field the editor emptied has to come
     * back empty — so what is written is the page's current values with the ones that travelled
     * laid over them. That is also what lets the form save one tab: the fields of the tabs
     * nobody touched are not in the request, and they must not be lost because of it.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Page $page, array $input, ?callable $can = null, ?int $authorId = null, string $source = EntityVersion::SOURCE_PANEL): void
    {
        $stored = $this->values->validate(self::SCREEN, $input, $can);

        // The home page's address is `''` in every language, and the model refuses anything
        // else. Dropped rather than refused, because a form that sends the whole screen back
        // should not have to know which field to leave out.
        if ($page->isRoot()) {
            unset($stored['slug']);
        }

        $draft = [...$this->draftable($this->values($page)), ...$this->draftable($stored)];

        $page->saveDraft($draft, $authorId, $source);

        // The one field on this screen that is not the page: it belongs to `module-seo`, which
        // put it here, and it goes to its own table rather than into the draft. Only when it
        // travelled — a save of the content tab alone must not empty a card nobody opened.
        if (array_key_exists(Fields::SCREEN, $stored)) {
            $value = $stored[Fields::SCREEN];

            $page->saveSeo(is_array($value) ? $value : null);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function draftable(array $values): array
    {
        return array_intersect_key($values, array_flip(self::OWN));
    }
}
