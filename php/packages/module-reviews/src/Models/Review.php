<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Categories\HasCategories;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Localization\HasTranslations;

/**
 * A review (§4.1 of the reviews spec): a photo, who wrote it, what they wrote and how many stars.
 *
 * No page of its own and no main category (decisions 2 and 5): a review reaches the site inside a
 * block or through `reviews()`, and its categories are only what those pick it by. No draft either
 * (decision 10) — `published` is the whole of its life.
 *
 * @property int $id
 * @property array<string, string>|string|null $name
 * @property array<string, string>|string|null $job_title
 * @property array<string, string>|string|null $text
 * @property int|null $rating
 * @property Carbon|null $reviewed_on
 * @property string|null $profile_url
 * @property array<string, mixed>|null $photo
 * @property bool $published
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Review extends Model
{
    use HasCategories;
    use HasExtra;
    use HasTranslations;
    use SoftDeletes;

    /** The screen a review is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'reviews.form';

    /** The most stars a review can have, and the fewest a rated one can. */
    public const MAX_RATING = 5;

    protected $table = 'reviews';

    /** @var list<string> */
    protected $fillable = ['name', 'job_title', 'text', 'rating', 'reviewed_on', 'profile_url', 'photo', 'published', 'position'];

    /** @var array<string, string> */
    protected $casts = [
        'published' => 'boolean',
        'position' => 'integer',
        'rating' => 'integer',
        'reviewed_on' => 'date',
        'photo' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(static function (Review $review): void {
            // At the end of the list: the only place a new review can go without moving one
            // somebody else put where it is.
            if (! array_key_exists('position', $review->getAttributes())) {
                $review->position = (int) static::query()->withTrashed()->max('position') + 1;
            }
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['name', 'job_title', 'text'];
    }

    /**
     * @return BelongsToMany<ReviewCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        /** @var BelongsToMany<ReviewCategory, $this> $relation */
        $relation = $this->belongsToCategories(ReviewCategory::class, 'review_category_review', 'review_id', 'category_id');

        return $relation;
    }

    public function categoryRelation(): string
    {
        return 'categories';
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /**
     * What a reader of a page in this language may be shown (decision 7): published, out of the
     * bin, and with a text in it. The query narrows by the key being there; {@see self::writtenIn()}
     * is the last word, because a key holding only spaces is not a text either.
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeVisibleIn(Builder $query, string $locale): Builder
    {
        return $query->where('published', true)->whereNotNull('text->'.$locale);
    }

    /** Whether a reader of a page in this language sees the review. */
    public function visibleIn(string $locale): bool
    {
        return $this->published && ! $this->trashed() && $this->writtenIn($locale);
    }

    /**
     * Whether the text is written in this language. No other language stands in for it: half a
     * block of reviews in another language is worse than a shorter one.
     */
    public function writtenIn(string $locale): bool
    {
        return $this->textIn($locale) !== '';
    }

    /** The text in this language, and only this one. */
    public function textIn(string $locale): string
    {
        $text = $this->getTranslation('text', $locale, fallback: false);

        return is_string($text) ? trim($text) : '';
    }

    /**
     * A name or a job title in this language, else in the default one (decision 7): a person's
     * name is mostly the same in every language, and hiding a review until somebody types it again
     * would be hiding it over a formality.
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

    /**
     * The categories the review is in, as ids — from what was loaded when it was.
     *
     * @return list<int>
     */
    public function categoryIds(): array
    {
        /** @var list<int> $ids */
        $ids = $this->categories->map(static fn (ReviewCategory $category): int => (int) $category->getKey())->values()->all();

        return $ids;
    }

    /** The library key of the photo, when there is one. */
    public function photoPath(): ?string
    {
        $path = is_array($this->photo) ? ($this->photo['path'] ?? null) : null;

        return is_string($path) && $path !== '' ? $path : null;
    }
}
