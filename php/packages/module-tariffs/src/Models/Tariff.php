<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Categories\HasCategories;
use WebxUi\Admin\Relations\HasRelations;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Localization\HasTranslations;

/**
 * A tariff (§5.1 of the tariffs spec): a price card — a name, a badge, a price in a currency or
 * words instead of one, what the plan includes, a description and one button.
 *
 * No page of its own (decision 1): a tariff reaches the site inside a block or through
 * `tariffs()`. No draft either (decision 10) — `published` is the whole of its life. Nobody is
 * hidden over a language (decision 12): the short words fall back to the default language, the
 * description and the rows of the list do not.
 *
 * `features` is not in {@see translatable()}: it is a list, and the languages are inside its rows.
 * The site's cards read it by language; the panel takes it raw (CLAUDE.md §4, «Переведено бывает и
 * то, на чём нет `localized`»).
 *
 * An owner of relations — the services a tariff is for — and not a target of any (decision 13).
 *
 * @property int $id
 * @property array<string, string>|string|null $name
 * @property array<string, string>|string|null $badge
 * @property float|null $price
 * @property string|null $currency
 * @property array<string, string>|string|null $period
 * @property array<string, string>|string|null $price_text
 * @property list<array<string, mixed>>|null $features
 * @property array<string, string>|string|null $description
 * @property array<string, string>|string|null $button_label
 * @property array<string, mixed>|null $button_link
 * @property string|null $button_variant
 * @property bool $featured
 * @property bool $published
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Tariff extends Model
{
    use HasCategories;
    use HasExtra;
    use HasRelations;
    use HasTranslations;
    use SoftDeletes;

    /** The screen a tariff is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'tariffs.form';

    /** The key relations of a tariff are stored under. */
    public const TYPE = 'tariff';

    /** The role of the services a tariff is for — the `name` of the field that edits it. */
    public const SERVICES = 'services';

    /** The translated columns. */
    public const WORDS = ['name', 'badge', 'period', 'price_text', 'description', 'button_label'];

    protected $table = 'tariffs';

    /** @var list<string> */
    protected $fillable = [
        'name', 'badge', 'price', 'currency', 'period', 'price_text', 'features', 'description',
        'button_label', 'button_link', 'button_variant', 'featured', 'published', 'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'price' => 'float',
        'features' => 'array',
        'button_link' => 'array',
        'featured' => 'boolean',
        'published' => 'boolean',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(static function (Tariff $tariff): void {
            // At the end of the list: the only place a new tariff can go without moving one
            // somebody else put where it is.
            if (! array_key_exists('position', $tariff->getAttributes())) {
                $tariff->position = (int) static::query()->withTrashed()->max('position') + 1;
            }
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return self::WORDS;
    }

    /**
     * @return BelongsToMany<TariffCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        /** @var BelongsToMany<TariffCategory, $this> $relation */
        $relation = $this->belongsToCategories(TariffCategory::class, 'tariff_category_tariff', 'tariff_id', 'category_id');

        return $relation;
    }

    public function categoryRelation(): string
    {
        return 'categories';
    }

    public function relationKey(): string
    {
        return self::TYPE;
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /**
     * What a reader may be shown (decision 12): published and out of the bin — in every language.
     *
     * @param  Builder<Tariff>  $query
     * @return Builder<Tariff>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('published'), true);
    }

    /**
     * The name, the badge, the period or the words instead of a price in this language, else in
     * the default one (decision 12): a price without "/mo" on a Russian page reads as a one-off
     * payment, which is worse than an English word.
     */
    public function wordsIn(string $field, string $locale, string $default): string
    {
        foreach (array_unique([$locale, $default]) as $code) {
            $words = $this->getTranslation($field, $code, fallback: false);

            if (is_string($words) && trim($words) !== '') {
                return trim($words);
            }
        }

        return '';
    }

    /** A field in this language and only this one — the description, the button's label. */
    public function textIn(string $field, string $locale): string
    {
        $text = $this->getTranslation($field, $locale, fallback: false);

        return is_string($text) ? trim($text) : '';
    }

    /**
     * The rows of "what is included" as they are stored, rows that are not one skipped.
     *
     * @return list<array<string, mixed>>
     */
    public function featureRows(): array
    {
        $rows = [];

        foreach (is_array($this->features) ? $this->features : [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * "What is included" in this language: a row not written in it drops out — the list is
     * shorter, not in another language (decision 12).
     *
     * @return list<string>
     */
    public function featuresIn(string $locale): array
    {
        $lines = [];

        foreach ($this->featureRows() as $row) {
            $text = $row['text'] ?? null;
            $words = is_array($text) ? ($text[$locale] ?? null) : $text;

            if (is_string($words) && trim($words) !== '') {
                $lines[] = trim($words);
            }
        }

        return $lines;
    }

    /**
     * The groups the tariff is in, as ids — from what was loaded when it was.
     *
     * @return list<int>
     */
    public function categoryIds(): array
    {
        /** @var list<int> $ids */
        $ids = $this->categories->map(static fn (TariffCategory $category): int => (int) $category->getKey())->values()->all();

        return $ids;
    }
}
