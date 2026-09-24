<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Models;

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
 * A category of reviews: a name, whether it is shown, and the fields of the project (decision 5).
 * No address, no SEO and no blocks — it is what a block picks "the reviews about implants" by and
 * a button in its filter, never a page.
 *
 * The shared category code makes an address out of the title when a category is created, because
 * most modules' categories want one. This one has nowhere to put it, so the slug is emptied on
 * every save, as with the FAQ's.
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
class ReviewCategory extends Model implements Category
{
    use HasExtra;
    use HasTranslations;
    use IsCategory;
    use SoftDeletes;

    /** The screen a category is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'reviews.category-form';

    /** Everything that writes a category. */
    public const MANAGE = 'reviews.categories.manage';

    protected $table = 'review_categories';

    /** @var list<string> */
    protected $fillable = ['title', 'is_visible', 'position'];

    protected static function booted(): void
    {
        static::saving(static function (ReviewCategory $category): void {
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
     * @return BelongsToMany<Review, $this>
     */
    public function reviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class, 'review_category_review', 'category_id', 'review_id')
            ->withPivot(['position', 'item_position']);
    }

    /**
     * @return BelongsToMany<Review, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->reviews();
    }

    /**
     * What a category of reviews is to the code every module's categories share: no prefix,
     * because there is no address to start.
     */
    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['reviews.view', 'reviews.manage', self::MANAGE],
            manage: self::MANAGE,
            noun: 'category',
            plural: 'categories',
            items: 'reviews',
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

    /** A category with reviews in it: deleting it would take a button out of every filter. */
    public function inUseMessage(int $count): string
    {
        return (string) trans('webx-reviews::errors.category-in-use', ['count' => $count]);
    }
}
