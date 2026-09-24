<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Models;

use Carbon\CarbonInterface;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Categories\HasCategories;
use WebxUi\Admin\Relations\HasRelations;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Localization\HasTranslations;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Recipes\Rendering\Similar;
use WebxUi\Recipes\Seo\RecipeMarkup;
use WebxUi\Recipes\Seo\Trail;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Contracts\HasStructuredData;
use WebxUi\Seo\HasSeo;

/**
 * A recipe: a page of fixed structure — no blocks (decision 2) — printed by the module's view.
 *
 * The draft and the history are `module-admin`'s, the address the registry's, what the page says
 * about itself `module-seo`'s. Everything the editor chooses waits in the draft until it is
 * published — the text, and also the categories, the nutrients, the services and the similar
 * recipes (§5.14): "linked the recipe to a service" shows on the site when the recipe is
 * published. The relations do that through {@see HasRelations}; the two kinds of category through
 * `category_ids` and `nutrient_ids` in the draft, which publishing hands back to
 * {@see setCategoryIdsAttribute()} and the save after it writes into the link tables.
 *
 * One order, `position` (decision 4): a category or a service shows its recipes in the order of
 * the whole list, and nobody drags them inside one.
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $lead
 * @property list<array<string, mixed>>|null $gallery
 * @property array<string, string>|string|null $ingredients
 * @property array<string, string>|string|null $method
 * @property array<string, array<string, string>>|null $nutrition
 * @property int|null $total_minutes
 * @property int|null $servings
 * @property int $position
 * @property array<string, mixed>|null $draft
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Recipe extends Model implements HasBreadcrumbs, HasStructuredData, Visible
{
    use HasCategories {
        scopeOrderedIn as private categoryOrderedIn;
    }
    use HasDraft;
    use HasExtra;
    use HasRelations;
    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use HasVersions {
        unversionedAttributes as private contentlessAttributes;
    }
    use SoftDeletes;

    /** The screen a recipe is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'recipes.form';

    /** The key the address registry and the relations know a recipe by. */
    public const TYPE = 'recipe';

    /** The nutrition a recipe states, in this order — the set the old site had (decision 7). */
    public const NUTRITION = ['calories', 'protein', 'fat', 'carbohydrates', 'fiber'];

    /** The draft's keys for the two kinds of category: they are rows, not columns. */
    public const DRAFT_CATEGORIES = 'category_ids';

    public const DRAFT_NUTRIENTS = 'nutrient_ids';

    /** Never on the site. */
    public const STATUS_DRAFT = 'draft';

    /** On the site, with nothing waiting. */
    public const STATUS_PUBLISHED = 'published';

    /** On the site, with edits that are not on it yet. */
    public const STATUS_MODIFIED = 'modified';

    /** Was on the site and was taken off it. */
    public const STATUS_UNPUBLISHED = 'unpublished';

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'lead', 'gallery', 'ingredients', 'method', 'nutrition', 'total_minutes', 'servings', 'position'];

    /** @var array<string, string> */
    protected $casts = [
        'gallery' => 'array',
        'nutrition' => 'array',
        'total_minutes' => 'integer',
        'servings' => 'integer',
        'position' => 'integer',
    ];

    /**
     * Categories published with the draft and not written yet — and what a preview copy shows.
     *
     * @var array<string, list<int>>
     */
    protected array $pendingCategories = [];

    protected static function booted(): void
    {
        static::creating(static function (Recipe $recipe): void {
            // At the end of the list: the only place a new recipe can go without moving one
            // somebody else put where it is.
            if (! array_key_exists('position', $recipe->getAttributes())) {
                $recipe->position = (int) static::query()->withTrashed()->max('position') + 1;
            }
        });

        static::saved(static function (Recipe $recipe): void {
            $pending = $recipe->pendingCategories;
            $recipe->pendingCategories = [];

            foreach ($pending as $relation => $ids) {
                $recipe->syncCategories($ids, $relation);
                $recipe->unsetRelation($relation);
            }
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug', 'lead', 'ingredients', 'method'];
    }

    /**
     * @return list<string>
     */
    public function unversionedAttributes(): array
    {
        // Where a recipe stands in the list is a decision about the catalogue, not about the
        // text: restoring an older version must not move it.
        return [...$this->contentlessAttributes(), 'position'];
    }

    public function relationKey(): string
    {
        return self::TYPE;
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /** A recipe has an address in a language when it names a slug in it. */
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /** Published and not in the bin — the handler's 404 and the sitemap's line alike. */
    public function isVisible(?string $locale = null): bool
    {
        return $this->isPublished() && ! $this->trashed();
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        return $query->whereNotNull($this->qualifyColumn($this->publishedAtColumn()));
    }

    /** Every publication stamps the date again, so it is when the page last changed. */
    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->published_at;
    }

    /**
     * Never published · on the site · on the site with edits · taken off it.
     */
    public function status(): string
    {
        if (! $this->isPublished()) {
            return $this->versions()->published()->exists() ? self::STATUS_UNPUBLISHED : self::STATUS_DRAFT;
        }

        return $this->hasDraft() ? self::STATUS_MODIFIED : self::STATUS_PUBLISHED;
    }

    /**
     * The categories, in the order the editor put them in. The first is the main one.
     *
     * @return BelongsToMany<RecipeCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        /** @var BelongsToMany<RecipeCategory, $this> $relation */
        $relation = $this->belongsToCategories(RecipeCategory::class, 'recipe_category_recipe', 'recipe_id', 'category_id');

        return $relation;
    }

    /**
     * What the recipe is rich in — the second kind of category (§3.8).
     *
     * @return BelongsToMany<RecipeNutrient, $this>
     */
    public function nutrients(): BelongsToMany
    {
        /** @var BelongsToMany<RecipeNutrient, $this> $relation */
        $relation = $this->belongsToCategories(RecipeNutrient::class, 'recipe_nutrient_recipe', 'recipe_id', 'category_id');

        return $relation;
    }

    public function categoryRelation(): string
    {
        return 'categories';
    }

    /**
     * Only the recipes of this category, and always in the order of the whole list — the one
     * order there is (decision 4). Shared code asks for "the order of one category" by this name.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeOrderedIn(Builder $query, ?int $category = null): Builder
    {
        if ($category !== null) {
            $this->scopeInCategory($query, $category);
        }

        return $this->categoryOrderedIn($query);
    }

    /**
     * The categories as the page shows them: what publishing is about to write when there is
     * something, the rows otherwise. A preview is a copy laid over with the draft, and it is
     * never saved — so this is the only place its categories are.
     *
     * @return Collection<int, RecipeCategory>
     */
    public function shownCategories(): Collection
    {
        return $this->shown('categories', RecipeCategory::class);
    }

    /**
     * @return Collection<int, RecipeNutrient>
     */
    public function shownNutrients(): Collection
    {
        return $this->shown('nutrients', RecipeNutrient::class);
    }

    /** The first category the page shows — the one the breadcrumbs go through — or none. */
    public function mainRecipeCategory(): ?RecipeCategory
    {
        $category = $this->shownCategories()->first();

        return $category instanceof RecipeCategory ? $category : null;
    }

    /**
     * The ids of one kind of category as the editor last left them: the draft's when it names
     * them, the rows otherwise. What a form opens with.
     *
     * @return list<int>
     */
    public function draftedCategoryIds(string $relation = 'categories'): array
    {
        $drafted = $this->draftValues()[self::draftKey($relation)] ?? null;

        if (is_array($drafted)) {
            return array_values(array_map(intval(...), $drafted));
        }

        return $this->categoryIds($relation);
    }

    /**
     * The ids of one kind of category as the site has them.
     *
     * @return list<int>
     */
    public function categoryIds(string $relation = 'categories'): array
    {
        /** @var list<int> $ids */
        $ids = $this->categoryLinks($relation)->pluck($this->categoryLinks($relation)->getRelated()->getQualifiedKeyName())
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }

    /** Where a published draft hands back its categories: written by the save that follows. */
    public function setCategoryIdsAttribute(mixed $value): void
    {
        $this->pendingCategories['categories'] = self::ids($value);
    }

    public function setNutrientIdsAttribute(mixed $value): void
    {
        $this->pendingCategories['nutrients'] = self::ids($value);
    }

    /** The key of the draft a kind of category waits under. */
    public static function draftKey(string $relation): string
    {
        return $relation === 'nutrients' ? self::DRAFT_NUTRIENTS : self::DRAFT_CATEGORIES;
    }

    /**
     * Only the five keys, each a map of languages with something in them. Anything else — a key
     * nobody declared, an empty language — is dropped here, so neither a draft nor an agent can
     * put it on the page (§4).
     */
    public function setNutritionAttribute(mixed $value): void
    {
        $clean = self::cleanNutrition($value);

        $this->attributes['nutrition'] = $clean === [] ? null : json_encode($clean, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function cleanNutrition(mixed $value): array
    {
        $clean = [];

        foreach (self::NUTRITION as $key) {
            $words = is_array($value) ? $value[$key] ?? null : null;

            if (! is_array($words)) {
                continue;
            }

            foreach ($words as $locale => $text) {
                if (is_string($locale) && is_scalar($text) && trim((string) $text) !== '') {
                    $clean[$key][$locale] = trim((string) $text);
                }
            }
        }

        return $clean;
    }

    /**
     * The nutrition in one language: only what is written in it, in the fixed order. No fallback
     * to another language — "12 g" is the same in all of them, but "a quarter of a day's need"
     * is not, and a table half in a foreign language is worse than a shorter one.
     *
     * @return array<string, string>
     */
    public function nutrition(string $locale): array
    {
        $stored = $this->nutrition;
        $out = [];

        foreach (self::NUTRITION as $key) {
            $text = is_array($stored) ? $stored[$key][$locale] ?? null : null;

            if (is_string($text) && trim($text) !== '') {
                $out[$key] = $text;
            }
        }

        return $out;
    }

    /**
     * The gallery as a template reads it — addresses and sizes worked out now, captions in one
     * language. The first is the cover.
     *
     * @return list<array<string, mixed>>
     */
    public function pictures(?string $locale = null): array
    {
        return Container::getInstance()->make(MediaValues::class)->resolveList($this->gallery ?? [], $locale);
    }

    /** @return array<string, mixed>|null */
    public function cover(?string $locale = null): ?array
    {
        $first = is_array($this->gallery) ? $this->gallery[0] ?? null : null;

        return $first === null ? null : Container::getInstance()->make(MediaValues::class)->resolve($first, $locale);
    }

    /**
     * One of the two documents as a page prints it: the library pictures in it pointed at where
     * they live now — the document holds keys, not addresses.
     */
    public function html(string $attribute, ?string $locale = null): string
    {
        $stored = $this->getTranslation($attribute, $locale ?? app()->getLocale(), false);

        if (! is_string($stored) || trim($stored) === '') {
            return '';
        }

        $type = app(FieldTypes::class)->get('wx-rich-text');
        $resolved = $type === null ? $stored : $type->resolve($stored, [], $locale);

        return is_string($resolved) ? $resolved : $stored;
    }

    /**
     * The recipes to show under this one (§5.6): chosen by hand, or picked by what they share.
     *
     * @return Collection<int, Recipe>
     */
    public function similar(?int $limit = null, ?string $locale = null): Collection
    {
        return Container::getInstance()->make(Similar::class)->to($this, $limit ?? (int) config('webx-recipes.similar', 3), $locale);
    }

    /**
     * Index → main category → the recipe. The category drops out when it is hidden or has no
     * address in this language: a step that leads to a 404 is worse than one step fewer.
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        $category = $this->mainRecipeCategory();
        $categoryCrumb = $category !== null && $category->isVisible($locale) && $category->hasUrlIn($locale)
            ? new Crumb((string) $category->getTranslation('title', $locale), $category->url($locale))
            : null;

        return Trail::of($locale, $categoryCrumb, new Crumb((string) $this->getTranslation('title', $locale), $this->url($locale)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function structuredData(string $locale): array
    {
        return [Container::getInstance()->make(RecipeMarkup::class)->of($this, $locale)];
    }

    /**
     * @template TCategory of RecipeCategory|RecipeNutrient
     *
     * @param  class-string<TCategory>  $model
     * @return Collection<int, TCategory>
     */
    private function shown(string $relation, string $model): Collection
    {
        if (! array_key_exists($relation, $this->pendingCategories)) {
            /** @var Collection<int, TCategory> $rows */
            $rows = $this->getRelationValue($relation);

            return $rows;
        }

        $ids = $this->pendingCategories[$relation];
        $found = $model::query()->whereKey($ids)->get()->keyBy(static fn (Model $row): int => (int) $row->getKey());
        $list = [];

        foreach ($ids as $id) {
            $row = $found->get($id);

            if ($row instanceof $model) {
                $list[] = $row;
            }
        }

        return new Collection($list);
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        $ids = [];

        foreach (is_array($value) ? $value : [] as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ids[(int) $id] = true;
            }
        }

        return array_keys($ids);
    }
}
