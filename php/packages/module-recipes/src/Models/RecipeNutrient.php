<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Admin\Categories\IsCategory;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Localization\HasTranslations;

/**
 * What a recipe is rich in — iron, fibre (decision 8): a chip on the recipe and a filter of the
 * catalogue, never a page. Called `nutrient` in the code because "source" is taken twice already
 * (decision 15).
 *
 * The shared category code makes an address out of the title when one is created, because most
 * modules' categories want one. This one has nowhere to put it, so the slug is emptied on every
 * save, as a FAQ category's is.
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property bool $is_visible
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RecipeNutrient extends Model implements Category
{
    use HasExtra;
    use HasTranslations;
    use IsCategory;
    use SoftDeletes;

    /** The screen a nutrient is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'recipes.nutrient-form';

    /** @var list<string> */
    protected $fillable = ['title', 'is_visible', 'position'];

    protected static function booted(): void
    {
        static::saving(static function (RecipeNutrient $nutrient): void {
            // Past the translations, which would read null as "empty in this language".
            $nutrient->attributes['slug'] = null;
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug'];
    }

    /**
     * @return BelongsToMany<Recipe, $this>
     */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_nutrient_recipe', 'category_id', 'recipe_id')
            ->withPivot(['position', 'item_position']);
    }

    /**
     * @return BelongsToMany<Recipe, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->recipes();
    }

    /** No prefix: there is no address to start. */
    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['recipes.view', 'recipes.manage', RecipeCategory::MANAGE],
            manage: RecipeCategory::MANAGE,
            noun: 'nutrient',
            plural: 'nutrients',
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
        return ['title', 'is_visible'];
    }

    public function inUseMessage(int $count): string
    {
        return (string) trans('webx-recipes::errors.nutrient-in-use', ['count' => $count]);
    }
}
