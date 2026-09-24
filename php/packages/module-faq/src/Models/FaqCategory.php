<?php

declare(strict_types=1);

namespace WebxUi\Faq\Models;

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
 * A category of questions: a name, whether it is shown, and the fields of the project (decision
 * 2). No address, no SEO and no blocks — it is a button in a block's filter and a way to pick
 * "the questions about payment" for a block, never a page.
 *
 * The shared category code makes an address out of the title when a category is created, because
 * every other module's categories want one. This one has nowhere to put it, so the slug is
 * emptied on every save rather than kept as a promise nothing answers.
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
class FaqCategory extends Model implements Category
{
    use HasExtra;
    use HasTranslations;
    use IsCategory;
    use SoftDeletes;

    /** The screen a category is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'faq.category-form';

    /** Everything that writes a category. */
    public const MANAGE = 'faq.categories.manage';

    protected $table = 'faq_categories';

    /** @var list<string> */
    protected $fillable = ['title', 'is_visible', 'position'];

    protected static function booted(): void
    {
        static::saving(static function (FaqCategory $category): void {
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
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'faq_category_question', 'category_id', 'question_id')
            ->withPivot(['position', 'item_position']);
    }

    /**
     * @return BelongsToMany<Question, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->questions();
    }

    /**
     * What a category of questions is to the code every module's categories share: no prefix,
     * because there is no address to start.
     */
    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['faq.view', 'faq.manage', self::MANAGE],
            manage: self::MANAGE,
            noun: 'category',
            plural: 'categories',
            items: 'questions',
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

    /** A category with questions in it: deleting it would take a button out of every filter. */
    public function inUseMessage(int $count): string
    {
        return (string) trans('webx-faq::errors.category-in-use', ['count' => $count]);
    }
}
