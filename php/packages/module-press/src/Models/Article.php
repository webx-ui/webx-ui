<?php

declare(strict_types=1);

namespace WebxUi\Press\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Localization\HasTranslations;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Press\Support\Kinds;

/**
 * An article in an outlet (§4.1 of the press spec): its title, a few words, a kind, a date, and
 * where it is — an address, a PDF from the library, or both (decision 8).
 *
 * No page of its own and no publication of its own (decision 9): it is seen when its outlet is,
 * when it has a title in the language being read (decision 7), and when nobody set it aside with
 * `is_hidden`.
 *
 * Whenever what it takes to see it changes — a title in a language, the flag, the article being
 * there at all — the outlet's addresses are worked out again: an outlet has an address only in the
 * languages it has something to show in ({@see Outlet::hasUrlIn()}).
 *
 * @property int $id
 * @property int $outlet_id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $excerpt
 * @property string|null $kind
 * @property Carbon|null $published_on
 * @property string $date_precision
 * @property string|null $url
 * @property array<string, mixed>|null $file
 * @property bool $is_hidden
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Outlet|null $outlet
 */
class Article extends Model
{
    use HasExtra;
    use HasTranslations;

    public const DAY = 'day';

    public const MONTH = 'month';

    public const YEAR = 'year';

    /** How much of the date is known (decision 10), the finest first. */
    public const PRECISIONS = [self::DAY, self::MONTH, self::YEAR];

    /** The translated columns. */
    public const TRANSLATED = ['title', 'excerpt'];

    /** What makes an article seen or not, and so what moves its outlet's addresses. */
    private const VISIBILITY = ['title', 'is_hidden', 'outlet_id'];

    protected $table = 'press_articles';

    /** @var list<string> */
    protected $fillable = [
        'outlet_id', 'title', 'excerpt', 'kind', 'published_on', 'date_precision', 'url', 'file', 'is_hidden', 'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'outlet_id' => 'integer',
        'published_on' => 'date',
        'file' => 'array',
        'is_hidden' => 'boolean',
        'position' => 'integer',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'date_precision' => self::DAY,
        'is_hidden' => false,
    ];

    protected static function booted(): void
    {
        static::creating(static function (Article $article): void {
            // At the end of its outlet: the only place a new article can go without moving one
            // somebody else put where it is.
            if (! array_key_exists('position', $article->getAttributes())) {
                $article->position = (int) static::query()->where('outlet_id', $article->outlet_id)->max('position') + 1;
            }
        });

        static::created(static function (Article $article): void {
            $article->outletChanged();
        });

        static::updated(static function (Article $article): void {
            if ($article->wasChanged(self::VISIBILITY)) {
                $article->outletChanged();

                // Moved to another outlet: the one it left may have lost its last article too.
                $before = $article->getOriginal('outlet_id');

                if ($article->wasChanged('outlet_id') && is_numeric($before)) {
                    Outlet::withTrashed()->find((int) $before)?->syncAddresses();
                }
            }
        });

        static::deleted(static function (Article $article): void {
            $article->outletChanged();
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return self::TRANSLATED;
    }

    /** A project's fields live on the outlet's screen, in the row of the articles repeater. */
    public function extraScreen(): string
    {
        return Outlet::SCREEN;
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id')->withTrashed();
    }

    /** One of the three, and a day for anything else. */
    public function setDatePrecisionAttribute(mixed $value): void
    {
        $this->attributes['date_precision'] = is_string($value) && in_array($value, self::PRECISIONS, true) ? $value : self::DAY;
    }

    /**
     * What a reader of a page in this language may be shown, of the articles of an outlet already
     * chosen: not set aside, and titled in the language. {@see self::visibleIn()} is the last word,
     * because a title of spaces is not a title either.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopeVisibleIn(Builder $query, string $locale): Builder
    {
        return $query->where($this->qualifyColumn('is_hidden'), false)->whereNotNull($this->qualifyColumn('title').'->'.$locale);
    }

    /**
     * In the order of the feed (decision 6): the latest first, the ones without a date last.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopeByDate(Builder $query): Builder
    {
        $date = $this->qualifyColumn('published_on');

        return $query
            ->orderByRaw("case when {$date} is null then 1 else 0 end")
            ->orderByDesc($date)
            ->orderByDesc($this->qualifyColumn('id'));
    }

    /** Whether a reader of a page in this language sees the article, its outlet aside. */
    public function visibleIn(string $locale): bool
    {
        return ! $this->is_hidden && $this->text('title', $locale) !== '';
    }

    /** One translated column in one language, with no fallback — '' where it is not written. */
    public function text(string $attribute, string $locale): string
    {
        $value = $this->getTranslation($attribute, $locale, false);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * The kind, when it is still one the site has (decision 3) — a key taken out of the config is
     * no kind rather than a word nobody wrote.
     */
    public function kindKey(): ?string
    {
        return Kinds::has($this->kind) ? $this->kind : null;
    }

    /** Where the title leads: the address, else the PDF (decision 8). */
    public function target(?string $locale = null): ?string
    {
        return $this->link() ?? $this->pdf($locale);
    }

    /** The address, when there is one. */
    public function link(): ?string
    {
        return is_string($this->url) && $this->url !== '' ? $this->url : null;
    }

    /** The address of the PDF as the library has it now, or null — also when the file is gone. */
    public function pdf(?string $locale = null): ?string
    {
        if ($this->filePath() === null) {
            return null;
        }

        $file = Container::getInstance()->make(MediaValues::class)->resolve($this->file, $locale);
        $url = is_array($file) ? ($file['url'] ?? null) : null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /** The library key of the PDF, when there is one. */
    public function filePath(): ?string
    {
        $path = is_array($this->file) ? ($this->file['path'] ?? null) : null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /** Its outlet's addresses, worked out again — when there is an outlet to ask. */
    private function outletChanged(): void
    {
        Outlet::withTrashed()->find($this->outlet_id)?->syncAddresses();
    }
}
