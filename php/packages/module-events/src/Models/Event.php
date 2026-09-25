<?php

declare(strict_types=1);

namespace WebxUi\Events\Models;

use Carbon\CarbonInterface;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Categories\HasCategories;
use WebxUi\Admin\Relations\HasRelations;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Events\Seo\EventMarkup;
use WebxUi\Events\Seo\Trail;
use WebxUi\Events\Support\Moment;
use WebxUi\Localization\HasTranslations;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Contracts\HasStructuredData;
use WebxUi\Seo\HasSeo;

/**
 * An event: a page of fixed structure — no blocks (decision 1) — printed by the module's view.
 *
 * The draft and the history are `module-admin`'s, the address the registry's, what the page says
 * about itself `module-seo`'s. Everything the editor chooses waits in the draft until it is
 * published — the text, and also the categories and the services: the categories through
 * `category_ids` in the draft, which publishing hands back to {@see setCategoryIdsAttribute()},
 * the services through {@see HasRelations}.
 *
 * The order is the date (decision 4). An event without a start is one whose date is still to be
 * settled: always among the ones to come, first of them, never a past one (decision 3). An event
 * is past when its end — its start where it names no end — is behind us (decision 7).
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $lead
 * @property list<array<string, mixed>>|null $gallery
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $all_day
 * @property array<string, string>|string|null $date_note
 * @property string $attendance
 * @property array<string, string>|string|null $venue
 * @property array<string, string>|string|null $address
 * @property string|null $map_url
 * @property array<string, string>|string|null $description
 * @property list<array<string, mixed>>|null $highlights
 * @property array<string, string>|string|null $price
 * @property float|null $price_amount
 * @property string|null $booking_url
 * @property array<string, mixed>|null $draft
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Event extends Model implements HasBreadcrumbs, HasStructuredData, Visible
{
    use HasCategories;
    use HasDraft;
    use HasExtra;
    use HasRelations;
    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use HasVersions;
    use SoftDeletes;

    /** The screen an event is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'events.form';

    /** The key the address registry and the relations know an event by. */
    public const TYPE = 'event';

    /** The draft's key for the categories: they are rows, not a column. */
    public const DRAFT_CATEGORIES = 'category_ids';

    /** The role the form keeps its services under (`wx-relations` named `services`). */
    public const SERVICES = 'services';

    /** Where the event happens (decision 16). */
    public const OFFLINE = 'offline';

    public const ONLINE = 'online';

    public const MIXED = 'mixed';

    public const ATTENDANCE = [self::OFFLINE, self::ONLINE, self::MIXED];

    /** Never on the site. */
    public const STATUS_DRAFT = 'draft';

    /** On the site, with nothing waiting. */
    public const STATUS_PUBLISHED = 'published';

    /** On the site, with edits that are not on it yet. */
    public const STATUS_MODIFIED = 'modified';

    /** Was on the site and was taken off it. */
    public const STATUS_UNPUBLISHED = 'unpublished';

    /** The translated columns. */
    public const TRANSLATED = ['title', 'slug', 'lead', 'date_note', 'venue', 'address', 'description', 'price'];

    /** @var list<string> */
    protected $fillable = [
        'title', 'slug', 'lead', 'gallery', 'starts_at', 'ends_at', 'all_day', 'date_note', 'attendance',
        'venue', 'address', 'map_url', 'description', 'highlights', 'price', 'price_amount', 'booking_url',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'gallery' => 'array',
        'highlights' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'price_amount' => 'float',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'all_day' => false,
        'attendance' => self::OFFLINE,
    ];

    /**
     * Categories published with the draft and not written yet — and what a preview copy shows.
     *
     * @var list<int>|null
     */
    protected ?array $pendingCategories = null;

    protected static function booted(): void
    {
        static::saving(static function (Event $event): void {
            $event->settleDays();
        });

        static::saved(static function (Event $event): void {
            $pending = $event->pendingCategories;
            $event->pendingCategories = null;

            if ($pending !== null) {
                $event->syncCategories($pending);
                $event->unsetRelation('categories');
            }
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return self::TRANSLATED;
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
     * There is no order to drag (decision 4): a category lists its events by their date, and the
     * shared code asks for the order of a new link by this.
     *
     * @return array<string, 'asc'|'desc'>
     */
    public function categoryItemOrder(): array
    {
        return [$this->getKeyName() => 'asc'];
    }

    /** Written into the application's timezone, whatever offset it came with (decision 15). */
    public function setStartsAtAttribute(mixed $value): void
    {
        $moment = Moment::from($value);

        $this->attributes['starts_at'] = $moment === null ? null : $this->fromDateTime($moment);
    }

    public function setEndsAtAttribute(mixed $value): void
    {
        $moment = Moment::from($value);

        $this->attributes['ends_at'] = $moment === null ? null : $this->fromDateTime($moment);
    }

    /** One of the three kinds, and the first of them for anything else. */
    public function setAttendanceAttribute(mixed $value): void
    {
        $this->attributes['attendance'] = is_string($value) && in_array($value, self::ATTENDANCE, true) ? $value : self::OFFLINE;
    }

    /**
     * An event of days covers them whole: its start is the first midnight and its end the last
     * moment of its last day — one day when it names no end. What makes "past" one expression
     * for both kinds (decision 7): a day's event is not over at the midnight it begins.
     */
    public function settleDays(): void
    {
        if (! $this->all_day || $this->starts_at === null) {
            return;
        }

        $start = $this->starts_at->copy()->startOfDay();
        $end = ($this->ends_at ?? $this->starts_at)->copy()->endOfDay()->startOfSecond();

        $this->starts_at = $start;
        $this->ends_at = $end->lessThan($start) ? $start->copy()->endOfDay()->startOfSecond() : $end;
    }

    /** An event has an address in a language when it names a slug in it. */
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /**
     * Published, not in the bin — and, asked about a language, titled in it: a page with no
     * heading is not a page to show (§4.4). The handler's 404 and the sitemap's line alike.
     */
    public function isVisible(?string $locale = null): bool
    {
        return $this->isPublished()
            && ! $this->trashed()
            && ($locale === null || $this->hasTranslation('title', $locale));
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        return $query->whereNotNull($this->qualifyColumn($this->publishedAtColumn()));
    }

    /**
     * The ones to come (decisions 3, 7): without a date first, then from the nearest. An event
     * that has begun and not ended is still to come — somebody may still be on the way to it.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeUpcoming(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $starts = $this->qualifyColumn('starts_at');
        $ends = $this->qualifyColumn('ends_at');
        $now = Moment::from($now ?? Carbon::now());

        return $query
            ->where(static function (Builder $open) use ($starts, $ends, $now): void {
                $open->whereNull($starts)->orWhereRaw("coalesce({$ends}, {$starts}) >= ?", [$now]);
            })
            ->orderByRaw("case when {$starts} is null then 0 else 1 end")
            ->orderBy($starts)
            ->orderBy($this->qualifyColumn($this->getKeyName()));
    }

    /**
     * The ones that are over, from the last. Never one without a date.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopePast(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $starts = $this->qualifyColumn('starts_at');
        $ends = $this->qualifyColumn('ends_at');
        $now = Moment::from($now ?? Carbon::now());

        return $query
            ->whereNotNull($starts)
            ->whereRaw("coalesce({$ends}, {$starts}) < ?", [$now])
            ->orderByDesc($starts)
            ->orderByDesc($this->qualifyColumn($this->getKeyName()));
    }

    /**
     * Every one, by date: the ones without first, then the latest start first — the order of
     * the panel's "all" and of `events()->all()`.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeByDate(Builder $query): Builder
    {
        $starts = $this->qualifyColumn('starts_at');

        return $query
            ->orderByRaw("case when {$starts} is null then 0 else 1 end")
            ->orderByDesc($starts)
            ->orderByDesc($this->qualifyColumn($this->getKeyName()));
    }

    /** Whether it is over — the same expression as {@see scopePast()}, asked of one event. */
    public function isPast(?CarbonInterface $now = null): bool
    {
        $end = $this->ends_at ?? $this->starts_at;

        return $end !== null && $end->lessThan($now ?? Carbon::now());
    }

    /** Every publication stamps the date again, so it is when the page last changed. */
    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->published_at;
    }

    /**
     * Never published · on the site · on the site with edits · taken off it.
     */
    public function status(): string
    {
        if (! $this->isPublished()) {
            return $this->versions()->published()->exists() ? self::STATUS_UNPUBLISHED : self::STATUS_DRAFT;
        }

        return $this->hasDraft() ? self::STATUS_MODIFIED : self::STATUS_PUBLISHED;
    }

    /**
     * The categories, in the order the editor put them in. The first is the main one.
     *
     * @return BelongsToMany<EventCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        /** @var BelongsToMany<EventCategory, $this> $relation */
        $relation = $this->belongsToCategories(EventCategory::class, 'event_category_event', 'event_id', 'category_id');

        return $relation;
    }

    public function categoryRelation(): string
    {
        return 'categories';
    }

    /**
     * The categories as the page shows them: what publishing is about to write when there is
     * something, the rows otherwise. A preview is a copy laid over with the draft, and it is
     * never saved — so this is the only place its categories are.
     *
     * @return Collection<int, EventCategory>
     */
    public function shownCategories(): Collection
    {
        if ($this->pendingCategories === null) {
            /** @var Collection<int, EventCategory> $rows */
            $rows = $this->getRelationValue('categories');

            return $rows;
        }

        $ids = $this->pendingCategories;
        $found = EventCategory::query()->whereKey($ids)->get()->keyBy(static fn (EventCategory $row): int => (int) $row->getKey());
        $list = [];

        foreach ($ids as $id) {
            $row = $found->get($id);

            if ($row instanceof EventCategory) {
                $list[] = $row;
            }
        }

        return new Collection($list);
    }

    /** The first category the page shows — the one the breadcrumbs go through — or none. */
    public function mainEventCategory(): ?EventCategory
    {
        $category = $this->shownCategories()->first();

        return $category instanceof EventCategory ? $category : null;
    }

    /**
     * The categories as the editor last left them: the draft's when it names them, the rows
     * otherwise. What a form opens with.
     *
     * @return list<int>
     */
    public function draftedCategoryIds(): array
    {
        $drafted = $this->draftValues()[self::DRAFT_CATEGORIES] ?? null;

        if (is_array($drafted)) {
            return array_values(array_map(intval(...), $drafted));
        }

        return $this->categoryIds();
    }

    /**
     * The categories as the site has them.
     *
     * @return list<int>
     */
    public function categoryIds(): array
    {
        $relation = $this->categories();

        /** @var list<int> $ids */
        $ids = $relation->pluck($relation->getRelated()->getQualifiedKeyName())
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }

    /** Where a published draft hands back its categories: written by the save that follows. */
    public function setCategoryIdsAttribute(mixed $value): void
    {
        $ids = [];

        foreach (is_array($value) ? $value : [] as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ids[(int) $id] = true;
            }
        }

        $this->pendingCategories = array_keys($ids);
    }

    /**
     * "What to expect" in one language (§3): the cards in their order, each with what is written
     * in that language. A card with neither a title nor a text in it is left out — no fallback to
     * another language, for the reason the recipes give about nutrition: a card half in a foreign
     * language is worse than one card fewer.
     *
     * @return list<array{title: string, text: string}>
     */
    public function highlights(string $locale): array
    {
        $cards = [];

        foreach (is_array($this->highlights) ? $this->highlights : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = self::word($row['title'] ?? null, $locale);
            $text = self::word($row['text'] ?? null, $locale);

            if ($title !== '' || $text !== '') {
                $cards[] = ['title' => $title, 'text' => $text];
            }
        }

        return $cards;
    }

    /**
     * The gallery as a template reads it — addresses and sizes worked out now, captions in one
     * language. The first is the cover.
     *
     * @return list<array<string, mixed>>
     */
    public function pictures(?string $locale = null): array
    {
        return Container::getInstance()->make(MediaValues::class)->resolveList($this->gallery ?? [], $locale);
    }

    /** @return array<string, mixed>|null */
    public function cover(?string $locale = null): ?array
    {
        $first = is_array($this->gallery) ? $this->gallery[0] ?? null : null;

        return $first === null ? null : Container::getInstance()->make(MediaValues::class)->resolve($first, $locale);
    }

    /**
     * The description as a page prints it: the library pictures in it pointed at where they live
     * now — the document holds keys, not addresses.
     */
    public function descriptionHtml(?string $locale = null): string
    {
        $stored = $this->getTranslation('description', $locale ?? app()->getLocale(), false);

        if (! is_string($stored) || trim($stored) === '') {
            return '';
        }

        $type = app(FieldTypes::class)->get('wx-rich-text');
        $resolved = $type === null ? $stored : $type->resolve($stored, [], $locale);

        return is_string($resolved) ? $resolved : $stored;
    }

    /** One translated column in one language, with no fallback — '' where it is not written. */
    public function text(string $attribute, string $locale): string
    {
        $value = $this->getTranslation($attribute, $locale, false);

        return is_string($value) ? trim($value) : '';
    }

    /** Where the place is printed at all: an online event has none (decision 16). */
    public function hasPlace(): bool
    {
        return $this->attendance !== self::ONLINE;
    }

    /**
     * Index → main category → the event. The category drops out when it is hidden or has no
     * address in this language: a step that leads to a 404 is worse than one step fewer.
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        $category = $this->mainEventCategory();
        $categoryCrumb = $category !== null && $category->isVisible($locale) && $category->hasUrlIn($locale)
            ? new Crumb((string) $category->getTranslation('title', $locale), $category->url($locale))
            : null;

        return Trail::of($locale, $categoryCrumb, new Crumb((string) $this->getTranslation('title', $locale), $this->url($locale)));
    }

    /**
     * `Event`, only for an event with a start: `startDate` is required, and an event whose date
     * is words has nothing to put there (decision 3).
     *
     * @return list<array<string, mixed>>
     */
    public function structuredData(string $locale): array
    {
        if ($this->starts_at === null) {
            return [];
        }

        return [Container::getInstance()->make(EventMarkup::class)->of($this, $locale)];
    }

    private static function word(mixed $value, string $locale): string
    {
        if (is_array($value)) {
            $value = $value[$locale] ?? null;
        }

        return is_string($value) ? trim($value) : '';
    }
}
