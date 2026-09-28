<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\CategoryKind;
use WebxUi\Admin\Categories\IsCategory;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Admin\Screens\Types\SlugType;
use WebxUi\Localization\HasTranslations;

/**
 * A category of vacancies — "Development", "Sales": a group and a filter of the index and of
 * `vacancies()`, never a page (decision 2). No address, no SEO.
 *
 * The slug is kept and held unique all the same (decision 11): it is the key of the filter
 * (`?category=development`, `vacancies()->in('development')`), and the day a site asks for pages
 * of categories it becomes their address without moving any data (§7). Checked in `saving` — in
 * `created` or `updated` the row would already be written (CLAUDE.md §4) — for the form, an agent
 * and a script alike.
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
class VacancyCategory extends Model implements Category
{
    use HasExtra;
    use HasTranslations;
    use IsCategory;
    use SoftDeletes;

    /** The screen a category is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'vacancies.category-form';

    /** Everything that writes a category (§4.10). */
    public const MANAGE = 'vacancies.categories.manage';

    protected $table = 'vacancy_categories';

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'is_visible', 'position'];

    protected static function booted(): void
    {
        static::saving(static function (VacancyCategory $category): void {
            $category->checkSlugs();
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
     * @return BelongsToMany<Vacancy, $this>
     */
    public function vacancies(): BelongsToMany
    {
        return $this->belongsToMany(Vacancy::class, 'vacancy_category_vacancy', 'category_id', 'vacancy_id')
            ->withPivot(['position', 'item_position']);
    }

    /**
     * @return BelongsToMany<Vacancy, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->vacancies();
    }

    /**
     * What a category of vacancies is to the code every module's categories share: no prefix,
     * because there is no address to start (decision 2).
     */
    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: self::SCREEN,
            view: ['vacancies.view', 'vacancies.manage', self::MANAGE],
            manage: self::MANAGE,
            noun: 'category',
            plural: 'categories',
            items: 'vacancies',
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
        return ['title', 'slug', 'is_visible'];
    }

    /** A category with vacancies in it: deleting it would take a group out of the index. */
    public function inUseMessage(int $count): string
    {
        return (string) trans('webx-vacancies::errors.category-in-use', ['count' => $count]);
    }

    /**
     * Every language's slug: the shape of an address, and nobody else's among the categories of
     * vacancies — the bin counted, because a category back from it takes its slug back.
     *
     * @throws ValidationException
     */
    public function checkSlugs(): void
    {
        $errors = [];

        foreach ($this->getTranslations('slug') as $locale => $slug) {
            if (! is_string($slug) || trim($slug) === '') {
                continue;
            }

            $key = 'slug.'.$locale;
            $check = Validator::make(['value' => $slug], ['value' => SlugType::checks()]);

            if ($check->fails()) {
                $errors[$key] = (string) $check->errors()->first('value');

                continue;
            }

            $taken = static::query()
                ->withTrashed()
                ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
                ->where('slug->'.$locale, $slug)
                ->exists();

            if ($taken) {
                $errors[$key] = (string) __('webx-vacancies::errors.category-slug-taken');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * A category by its slug in a language — the key of the index's filter.
     */
    public static function findBySlug(string $slug, string $locale): ?self
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        /** @var self|null $found */
        $found = static::query()->where('slug->'.$locale, $slug)->first();

        return $found instanceof Model ? $found : null;
    }
}
