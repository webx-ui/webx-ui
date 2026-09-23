<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\Localization\HasTranslations;

/**
 * A category of one module: flat, ordered by hand, several per item.
 *
 * The code is shared and the data is not (§2.3 of the services spec): every module keeps its own
 * table — `$table->category()` — and its own link table, and this trait is what they have in
 * common. The model also uses {@see HasTranslations}, `SoftDeletes` and, for fields of the
 * project, `HasExtra`; an address, SEO and a cover are the module's to add, with the same traits
 * its items use.
 *
 *     class Rubric extends Model
 *     {
 *         use HasExtra, HasTranslations, IsCategory, SoftDeletes;
 *
 *         public static function categoryKind(): CategoryKind { ... }
 *         public function items(): BelongsToMany { return $this->articles(); }
 *     }
 *
 * The one rule with teeth lives here rather than in a controller: a category that still holds
 * items refuses to go into the bin, and says how many — so an import and an agent hit the same
 * wall as the button does. A category that vanished with items in it would be a hole in the
 * navigation that nobody notices: the items go on answering and the menu is one entry short.
 *
 * @mixin Model
 * @mixin HasTranslations
 * @mixin SoftDeletes
 */
trait IsCategory
{
    /** What this module's categories are. */
    abstract public static function categoryKind(): CategoryKind;

    /**
     * What is filed under the category.
     *
     * @return BelongsToMany<covariant Model, $this>
     */
    abstract public function items(): BelongsToMany;

    public static function bootIsCategory(): void
    {
        static::creating(static function (Model $category): void {
            // At the end of the list, which is the only place a new category can go without
            // moving one somebody else put where it is.
            if (! array_key_exists('position', $category->getAttributes())) {
                $category->setAttribute('position', (int) $category->newQuery()->withoutGlobalScopes()->max('position') + 1);
            }
        });

        static::deleting(static function (Model $category): void {
            /** @var Model&self $category */
            if (method_exists($category, 'isForceDeleting') && $category->isForceDeleting()) {
                return;
            }

            $count = $category->itemCount();

            if ($count > 0) {
                throw new CategoryException($category->inUseMessage($count));
            }
        });
    }

    public function initializeIsCategory(): void
    {
        $this->mergeCasts(['is_visible' => 'boolean', 'position' => 'integer']);
    }

    /**
     * The fields of the category's screen that are this model's own, rather than fields of the
     * project (which go into `extra`). A module with a cover or an introduction adds them here
     * and says in {@see writeCategoryValue()} how each is written.
     *
     * @return list<string>
     */
    public function categoryFields(): array
    {
        return ['title', 'slug', 'is_visible'];
    }

    /** One of {@see categoryFields()}, as the screen edits it. */
    public function categoryValue(string $field): mixed
    {
        if (in_array($field, $this->translatable(), true)) {
            return $this->getTranslations($field);
        }

        return $this->getAttribute($field);
    }

    /**
     * Write one of {@see categoryFields()}, before the model is saved.
     *
     * A translated field travels as a map and is laid over what is there, language by language:
     * the form sends every language, an agent the ones it has, and neither means to delete the
     * rest. A bare string goes into the language the panel speaks.
     */
    public function writeCategoryValue(string $field, mixed $value): void
    {
        if (! in_array($field, $this->translatable(), true)) {
            $this->setAttribute($field, $field === 'is_visible' ? (bool) $value : $value);

            return;
        }

        if (is_array($value)) {
            $this->setAttribute($field, [...$this->getTranslations($field), ...$value]);

            return;
        }

        $this->setTranslation($field, app()->getLocale(), $value);
    }

    /**
     * After the save, for the fields a module stores somewhere else — the SEO card goes into its
     * own table, and only a row that exists can be pointed at.
     *
     * @param  array<string, mixed>  $values  Every own field that was sent.
     */
    public function categorySaved(array $values): void {}

    /** The words the refusal is said in. A module that calls its items something else says so. */
    public function inUseMessage(int $count): string
    {
        return (string) __('webx-admin::categories.in-use', ['count' => $count]);
    }

    /** How many items would be left without this category — the number the refusal names. */
    public function itemCount(): int
    {
        return $this->items()->count();
    }

    /**
     * The ones a reader can reach.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        return $query->where($this->qualifyColumn('is_visible'), true);
    }

    /**
     * The order an editor dragged them into: the menu of the site and the filters of the panel.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy($this->qualifyColumn('position'))->orderBy($this->qualifyColumn($this->getKeyName()));
    }

    /**
     * The number of items beside each row, as a subquery rather than a query per row.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeWithItemCount(Builder $query): Builder
    {
        return $query->withCount('items as '.static::categoryKind()->countKey());
    }

    /**
     * Title or slug containing the words, in any language.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(static function (Builder $nested) use ($like): void {
            $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
        });
    }

    /**
     * The name to show, and something to show when there is none: a category made in another
     * language would otherwise be an empty row in this one.
     */
    public function displayName(string $locale): string
    {
        foreach ([$this->getTranslation('title', $locale), $this->getTranslation('slug', $locale)] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$this->getKey();
    }
}
