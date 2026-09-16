<?php

declare(strict_types=1);

namespace WebxUi\Pages\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Blocks\HasBlocks;
use WebxUi\Localization\HasTranslations;
use WebxUi\NestedSet\HasNestedSet;
use WebxUi\Pages\Exceptions\PagesException;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\HasSeo;

/**
 * A page of the site.
 *
 * Seven traits and barely any code of its own, which is the point: the tree is
 * `webx-ui/nested-set`, the address is `webx-ui/routing`, the content is `webx-ui/module-blocks`,
 * the draft and the history are `webx-ui/module-admin`, what the page says about itself is
 * `webx-ui/module-seo`, the languages are `webx-ui/localization`. What this class adds is the
 * three rules that are about pages rather than about any of those: the home page is the root and
 * cannot be moved, deleted or given an address; there is only ever one of it; and deleting a page
 * puts its whole branch in the bin together (§7).
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property list<array<string, mixed>>|null $blocks
 * @property array<string, mixed>|null $draft
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $trashed_with
 * @property int $lft
 * @property int $rgt
 * @property int $depth
 * @property int|null $parent_id
 */
class Page extends Model
{
    use HasBlocks;
    use HasDraft;

    /**
     * The four placements are wrapped rather than used as they are: the home page has no
     * siblings and no other parent, so every one of them has to refuse it. `up()` and `down()`
     * go through `insertBefore()` and `insertAfter()`, so they are covered by the same guard.
     */
    use HasNestedSet {
        appendTo as private placeAppendTo;
        prependTo as private placePrependTo;
        insertBefore as private placeInsertBefore;
        insertAfter as private placeInsertAfter;
        saveAsRoot as private placeAsRoot;
    }

    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use HasVersions {
        unversionedAttributes as private contentlessAttributes;
    }
    use SoftDeletes;

    /** Never published. */
    public const STATUS_DRAFT = 'draft';

    /** On the site, with nothing waiting. */
    public const STATUS_PUBLISHED = 'published';

    /** On the site, with edits that are not on it yet. */
    public const STATUS_MODIFIED = 'modified';

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'blocks'];

    /**
     * A trashed page keeps its place in the tree rather than being refused the delete: it still
     * occupies its bounds, its descendants still stand inside it, and a restore puts it back
     * where it was. What happens to those descendants is decided below, not by the trait.
     */
    public function softDeletesInTree(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug'];
    }

    /**
     * @return list<string>
     */
    public function unversionedAttributes(): array
    {
        // Where the page is and whether it is in the bin are not content, so a version does not
        // carry them — restoring an old text must not move the page or bring it back from the
        // bin.
        return [...$this->contentlessAttributes(), 'trashed_with'];
    }

    /** The home page: the root of the tree, and the only node that has no parent. */
    public static function home(): ?static
    {
        /** @var static|null $root */
        $root = static::query()->roots()->orderBy('lft')->first();

        return $root;
    }

    /**
     * What may be done to this node, for the panel to draw and the API to enforce.
     *
     * Three separate answers rather than one "is the root", because the next page that has to
     * stay put — a search page, a 404 — will want two of the three and not the same two (§2,
     * decision 3).
     *
     * @return array{move: bool, delete: bool, address: bool}
     */
    public function capabilities(): array
    {
        $structural = ! $this->isRoot();

        return ['move' => $structural, 'delete' => $structural, 'address' => $structural];
    }

    /**
     * A page has an address in a language when it and every page above it name one (§8).
     *
     * The ancestors have to be asked because the address is built out of them: a page whose
     * parent has no Ukrainian slug has nothing to put in front of its own. Leaving the language
     * out is the honest answer — better than an address made of the English slugs standing in
     * front of content that was never translated.
     *
     * The home page always counts: its slug is empty in every language on purpose.
     */
    public function hasUrlIn(string $locale): bool
    {
        foreach ($this->pathFromRoot() as $node) {
            if (! $node->isRoot() && ! $node->hasTranslation('slug', $locale)) {
                return false;
            }
        }

        return true;
    }

    /** Never published · on the site · on the site with edits waiting (§2, decision 6). */
    public function status(): string
    {
        if (! $this->isPublished()) {
            return self::STATUS_DRAFT;
        }

        return $this->hasDraft() ? self::STATUS_MODIFIED : self::STATUS_PUBLISHED;
    }

    /**
     * Everything that went into the bin when this page did — itself included, deepest last.
     *
     * @return EloquentCollection<int, static>
     */
    public function trashedBranch(): EloquentCollection
    {
        /** @var EloquentCollection<int, static> $branch */
        $branch = static::onlyTrashed()
            ->where('trashed_with', $this->getKey())
            ->orderBy('lft')
            ->get();

        return $branch;
    }

    /**
     * Take the page and everything that went down with it back out of the bin.
     *
     * The address comes back on its own: a restore is a `save()` of a row whose `deleted_at`
     * went to null, and the registry listens to `updated`. If the address was taken while the
     * page was in the bin, that save is refused — which is the right answer (§7), and the
     * reason the whole branch is one transaction rather than a loop that half succeeds.
     */
    public function restoreBranch(): bool
    {
        return (bool) $this->getConnection()->transaction(function (): bool {
            $branch = $this->trashedBranch();

            $this->setAttribute('trashed_with', null);
            $restored = (bool) $this->restore();

            foreach ($branch as $node) {
                $node->setAttribute('trashed_with', null);
                $node->restore();
            }

            return $restored;
        });
    }

    /**
     * One transaction, because a delete is a branch: the descendants go in the bin first (each
     * with its own events, so each releases its address), and the page itself after them.
     *
     * @return bool|null
     */
    public function delete()
    {
        return $this->getConnection()->transaction(fn () => parent::delete());
    }

    public function appendTo(self $parent): bool
    {
        $this->assertMovable();

        return $this->placeAppendTo($parent);
    }

    public function prependTo(self $parent): bool
    {
        $this->assertMovable();

        return $this->placePrependTo($parent);
    }

    public function insertBefore(self $sibling): bool
    {
        $this->assertMovable();

        return $this->placeInsertBefore($sibling);
    }

    public function insertAfter(self $sibling): bool
    {
        $this->assertMovable();

        return $this->placeInsertAfter($sibling);
    }

    public function saveAsRoot(): bool
    {
        if (! $this->isRoot()) {
            $this->assertNoOtherRoot();
        }

        return $this->placeAsRoot();
    }

    protected static function booted(): void
    {
        static::creating(static function (self $page): void {
            // A page created with no parent is a root, and there is only ever one root: the
            // address space is flat, and two roots would be two home pages (§2, decision 4).
            if ($page->getAttribute('parent_id') === null && ! $page->isDetached()) {
                $page->assertNoOtherRoot();
            }
        });

        static::saving(static function (self $page): void {
            // The home page is the one address nobody writes: it is `''`, which is what the
            // registry calls the front page. Refused rather than quietly emptied, so that a
            // panel sending a slug for it hears about it.
            if ($page->getAttribute('parent_id') === null && ! $page->isDetached() && $page->hasSlug()) {
                throw PagesException::homeHasNoAddress();
            }
        });

        static::deleting(static function (self $page): void {
            if ($page->isForceDeleting()) {
                return;
            }

            if ($page->isRoot()) {
                throw PagesException::homeCannotBeDeleted();
            }

            // Already marked as part of a branch on its way down: the loop below is what is
            // deleting it, and it must not start a branch of its own.
            if ($page->getAttribute('trashed_with') !== null) {
                return;
            }

            $page->trashDescendants();
        });
    }

    /**
     * Put the whole subtree in the bin, one node at a time.
     *
     * One at a time and not with a bulk update, because it is the `deleted` event that releases
     * an address: a branch turned off in one statement would leave every address in it held by
     * a page nobody can see (§7). Which page's deletion did it goes into `trashed_with`, so a
     * restore brings back this branch and not whatever was deleted separately before it.
     */
    private function trashDescendants(): void
    {
        // From a fresh copy, because bounds go stale in memory: every page added under this one
        // since it was loaded widened its `rgt` in the table and nowhere else, and a subtree
        // asked for through the old bounds comes back empty.
        $fresh = static::withTrashed()->find($this->getKey());

        if ($fresh === null) {
            return;
        }

        foreach ($fresh->descendants()->get() as $node) {
            $node->setAttribute('trashed_with', $this->getKey());
            $node->saveQuietly();
            $node->delete();
        }
    }

    /** Does the page name an address in any language? */
    private function hasSlug(): bool
    {
        foreach ($this->getTranslations('slug') as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function assertMovable(): void
    {
        if ($this->exists && $this->isRoot()) {
            throw PagesException::homeCannotBeMoved();
        }
    }

    private function assertNoOtherRoot(): void
    {
        $taken = static::withTrashed()
            ->roots()
            ->when($this->exists, fn (Builder $query): Builder => $query->whereKeyNot($this->getKey()))
            ->exists();

        if ($taken) {
            throw PagesException::homeAlreadyExists();
        }
    }
}
