<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Models;

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
 * A group of tariffs — "For individuals", "For business": a name, whether it is shown, and the
 * fields of the project (decision 2). A copy of the reviews' category: no address, no SEO and no
 * blocks — it is what a block picks the tariffs by and a tab of a page of prices, never a page.
 *
 * In the code a category, in the words a group (decision 14): the tables and the API speak the
 * language of the shared category code, the editor and an agent see "groups".
 *
 * The shared code makes an address out of the title when a category is created; this one has
 * nowhere to put it, so the slug is emptied on every save, as with the reviews'.
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
class TariffCategory extends Model implements Category
{
    use HasExtra;
    use HasTranslations;
    use IsCategory;
    use SoftDeletes;

    /** The screen a group is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'tariffs.category-form';

    /** Everything that writes a group. */
    public const MANAGE = 'tariffs.groups.manage';

    protected $table = 'tariff_categories';

    /** @var list<string> */
    protected $fillable = ['title', 'is_visible', 'position'];

    protected static function booted(): void
    {
        static::saving(static function (TariffCategory $category): void {
            // Past the translations, which would read null as "empty in this language".
            $category->attributes['slug'] = null;
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
     * @return BelongsToMany<Tariff, $this>
     */
    public function tariffs(): BelongsToMany
    {
        return $this->belongsToMany(Tariff::class, 'tariff_category_tariff', 'category_id', 'tariff_id')
            ->withPivot(['position', 'item_position']);
    }

    /**
     * @return BelongsToMany<Tariff, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->tariffs();
    }

    /**
     * What a group of tariffs is to the code every module's categories share: no prefix, because
     * there is no address to start, and the module's own words — "group", "groups", "tariffs".
     */
    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['tariffs.view', 'tariffs.manage', self::MANAGE],
            manage: self::MANAGE,
            noun: 'group',
            plural: 'groups',
            items: 'tariffs',
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

    /** A group with tariffs in it: deleting it would take a tab off every page of prices. */
    public function inUseMessage(int $count): string
    {
        return (string) trans('webx-tariffs::errors.category-in-use', ['count' => $count]);
    }
}
