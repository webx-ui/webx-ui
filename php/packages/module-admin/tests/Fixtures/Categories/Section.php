<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures\Categories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Admin\Categories\IsCategory;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Localization\HasTranslations;

/**
 * A category the way a module without addresses would write one — a FAQ section: the shared
 * columns and nothing of its own.
 *
 * Not final, unlike the other fixtures: PHPStan does not see `$this` of a final class as the
 * `$this` the abstract `items()` of the trait promises, and reports two identical types as
 * incompatible.
 *
 * @property int $id
 * @property int $position
 * @property bool $is_visible
 */
class Section extends Model implements Category
{
    use HasExtra;
    use HasTranslations;
    use IsCategory;
    use SoftDeletes;

    public const SCREEN = 'things.section-form';

    protected $table = 'sections';

    protected $guarded = [];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug'];
    }

    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['things.view'],
            manage: 'things.manage',
            noun: 'section',
            plural: 'sections',
            items: 'entries',
        );
    }

    /**
     * @return BelongsToMany<covariant Model, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'entry_category', 'category_id', 'entry_id');
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }
}
