<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Models;

use Carbon\CarbonInterface;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Admin\Categories\IsCategory;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Localization\HasTranslations;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Recipes\Seo\Trail;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\HasSeo;

/**
 * A category of recipes: a page of the site with its own address, SEO, introduction and picture —
 * a category of services without the blocks (decision 5).
 *
 * Edited in place, no draft. Hidden, it answers 404 and drops out of the index, while its recipes
 * go on answering at their own addresses: they are not its property.
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $lead
 * @property array<string, mixed>|null $cover
 * @property bool $is_visible
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RecipeCategory extends Model implements Category, HasBreadcrumbs, Visible
{
    use HasExtra;
    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use IsCategory {
        categoryValue as sharedCategoryValue;
        writeCategoryValue as writeSharedCategoryValue;
    }
    use SoftDeletes;

    /** The screen a category is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'recipes.category-form';

    /** Everything that writes a category — and a nutrient: one job, one permission (§5.9). */
    public const MANAGE = 'recipes.categories.manage';

    /** The key the address registry knows a category by. */
    public const TYPE = 'recipe-category';

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'lead', 'cover', 'is_visible', 'position'];

    /** @var array<string, string> */
    protected $casts = ['cover' => 'array'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug', 'lead'];
    }

    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /** Shown, and not in the bin — the handler's 404 and the sitemap's line alike. */
    public function isVisible(?string $locale = null): bool
    {
        return $this->is_visible && ! $this->trashed();
    }

    /** A category has no publication of its own: its page changes when it is saved. */
    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->updated_at;
    }

    /**
     * Index → the category.
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        return Trail::of($locale, new Crumb((string) $this->getTranslation('title', $locale), $this->url($locale)));
    }

    /** The introduction as a page prints it: library pictures pointed at where they live now. */
    public function leadHtml(?string $locale = null): string
    {
        $stored = $this->getTranslation('lead', $locale ?? app()->getLocale(), false);

        if (! is_string($stored) || trim($stored) === '') {
            return '';
        }

        $type = app(FieldTypes::class)->get('wx-rich-text');
        $resolved = $type === null ? $stored : $type->resolve($stored, [], $locale);

        return is_string($resolved) ? $resolved : $stored;
    }

    /**
     * The picture as a template reads it: address, sizes, captions in one language.
     *
     * @return array<string, mixed>|null
     */
    public function picture(?string $locale = null): ?array
    {
        return $this->cover === null ? null : Container::getInstance()->make(MediaValues::class)->resolve($this->cover, $locale);
    }

    /**
     * @return BelongsToMany<Recipe, $this>
     */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_category_recipe', 'category_id', 'recipe_id')
            ->withPivot(['position', 'item_position']);
    }

    /**
     * @return BelongsToMany<Recipe, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->recipes();
    }

    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['recipes.view', 'recipes.manage', self::MANAGE],
            manage: self::MANAGE,
            prefix: static fn (): string => (string) config('webx-recipes.prefix', 'recipes'),
            noun: 'category',
            plural: 'categories',
            items: 'recipes',
        );
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /**
     * @return list<string>
     */
    public function categoryFields(): array
    {
        return ['title', 'slug', 'is_visible', 'lead', 'cover', 'seo'];
    }

    public function categoryValue(string $field): mixed
    {
        return match ($field) {
            'cover' => $this->cover,
            'seo' => $this->seoValue(),
            default => $this->sharedCategoryValue($field),
        };
    }

    public function writeCategoryValue(string $field, mixed $value): void
    {
        match ($field) {
            // Already through `wx-media`'s store(): the key and the captions, never an address.
            'cover' => $this->cover = is_array($value) && $value !== [] ? $value : null,
            // Into its own table, after the save: see categorySaved().
            'seo' => null,
            default => $this->writeSharedCategoryValue($field, $value),
        };
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function categorySaved(array $values): void
    {
        // Only when the card travelled: a save of another tab must not empty a card nobody opened.
        if (array_key_exists('seo', $values)) {
            $this->saveSeo(is_array($values['seo']) && $values['seo'] !== [] ? $values['seo'] : null);
        }
    }

    public function inUseMessage(int $count): string
    {
        return (string) trans('webx-recipes::errors.category-in-use', ['count' => $count]);
    }
}
