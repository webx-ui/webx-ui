<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Models;

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
use WebxUi\Localization\HasTranslations;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Contracts\HasSeoFallback;
use WebxUi\Seo\Contracts\HasStructuredData;
use WebxUi\Seo\HasSeo;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Vacancies\Seo\JobPostingMarkup;
use WebxUi\Vacancies\Seo\Trail;
use WebxUi\Vacancies\Support\Day;

/**
 * A vacancy: a page of fixed structure — no blocks (decision 6) — printed by the module's view.
 *
 * The draft and the history are `module-admin`'s, the address the registry's, what the page says
 * about itself `module-seo`'s. Everything the editor chooses waits in the draft until it is
 * published — the text, whether the hiring is closed, and also the categories and the application
 * form: the categories through `category_ids` in the draft, which publishing hands back to
 * {@see setCategoryIdsAttribute()}, the form through {@see HasRelations}.
 *
 * Closed is one expression for SQL and for php (decision 13): closed by hand, or its last day is
 * behind us in the application's timezone. A closed vacancy leaves the lists and keeps its page,
 * marked, without `JobPosting` and with `noindex` (decisions 4, 14). Off the site is something
 * else: the page is a 404.
 *
 * One order, `position`, dragged by hand; a restored version does not move a vacancy.
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $lead
 * @property string $workplace
 * @property array<string, string>|string|null $city
 * @property array<string, string>|string|null $address
 * @property string|null $country
 * @property list<string>|null $employment_types
 * @property array<string, string>|string|null $salary
 * @property float|null $salary_min
 * @property float|null $salary_max
 * @property string|null $salary_unit
 * @property string|null $salary_currency
 * @property array<string, string>|string|null $description
 * @property list<array<string, mixed>>|null $duties
 * @property list<array<string, mixed>>|null $requirements
 * @property list<array<string, mixed>>|null $benefits
 * @property bool $is_closed
 * @property Carbon|null $valid_through
 * @property Carbon|null $posted_at
 * @property int $position
 * @property array<string, mixed>|null $draft
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Vacancy extends Model implements HasBreadcrumbs, HasSeoFallback, HasStructuredData, Visible
{
    use HasCategories;
    use HasDraft;
    use HasExtra;
    use HasRelations;
    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use HasVersions {
        unversionedAttributes as private contentlessAttributes;
    }
    use SoftDeletes;

    /** The screen a vacancy is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'vacancies.form';

    /** The key the address registry and the relations know a vacancy by. */
    public const TYPE = 'vacancy';

    /** The draft's key for the categories: they are rows, not a column. */
    public const DRAFT_CATEGORIES = 'category_ids';

    /** The role the application form is kept under (`wx-relations` named `form`, decision 10). */
    public const FORM = 'form';

    /** The key `module-inbox` registers its forms under as a target of relations. */
    public const FORM_TARGET = 'inbox-form';

    /** Where the work is done (decision 16). */
    public const ONSITE = 'onsite';

    public const REMOTE = 'remote';

    public const HYBRID = 'hybrid';

    public const WORKPLACES = [self::ONSITE, self::REMOTE, self::HYBRID];

    /** Google's list of `employmentType`, exactly (decision 17). */
    public const EMPLOYMENT = ['FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'TEMPORARY', 'INTERN', 'VOLUNTEER', 'PER_DIEM', 'OTHER'];

    /** What a salary is paid for — `unitText` of schema.org. */
    public const UNITS = ['HOUR', 'DAY', 'WEEK', 'MONTH', 'YEAR'];

    /** The three lists of lines (decision 18). */
    public const LISTS = ['duties', 'requirements', 'benefits'];

    /** Closed by hand, and past its last day. */
    public const CLOSED_MANUAL = 'manual';

    public const CLOSED_EXPIRED = 'expired';

    /** Never on the site. */
    public const STATUS_DRAFT = 'draft';

    /** On the site, with nothing waiting. */
    public const STATUS_PUBLISHED = 'published';

    /** On the site, with edits that are not on it yet. */
    public const STATUS_MODIFIED = 'modified';

    /** Was on the site and was taken off it. */
    public const STATUS_UNPUBLISHED = 'unpublished';

    /** The translated columns. */
    public const TRANSLATED = ['title', 'slug', 'lead', 'city', 'address', 'salary', 'description'];

    /** @var list<string> */
    protected $fillable = [
        'title', 'slug', 'lead', 'workplace', 'city', 'address', 'country', 'employment_types', 'salary',
        'salary_min', 'salary_max', 'salary_unit', 'salary_currency', 'description', 'duties', 'requirements',
        'benefits', 'is_closed', 'valid_through', 'posted_at', 'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'employment_types' => 'array',
        'duties' => 'array',
        'requirements' => 'array',
        'benefits' => 'array',
        'salary_min' => 'float',
        'salary_max' => 'float',
        'is_closed' => 'boolean',
        'valid_through' => 'date',
        'posted_at' => 'date',
        'position' => 'integer',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'workplace' => self::ONSITE,
        'is_closed' => false,
    ];

    /**
     * Categories published with the draft and not written yet — and what a preview copy shows.
     *
     * @var list<int>|null
     */
    protected ?array $pendingCategories = null;

    protected static function booted(): void
    {
        static::creating(static function (Vacancy $vacancy): void {
            // At the end of the list, the bin counted: the only place a new vacancy can go
            // without moving one somebody else put where it is.
            if (! array_key_exists('position', $vacancy->getAttributes())) {
                $vacancy->position = (int) static::query()->withTrashed()->max('position') + 1;
            }
        });

        static::saving(static function (Vacancy $vacancy): void {
            // The day it was first put up (decision 15): the first publication, and never again
            // while the editor leaves it as it is.
            if ($vacancy->isPublished() && $vacancy->posted_at === null) {
                $vacancy->posted_at = Carbon::parse(Day::today());
            }
        });

        static::saved(static function (Vacancy $vacancy): void {
            $pending = $vacancy->pendingCategories;
            $vacancy->pendingCategories = null;

            if ($pending !== null) {
                $vacancy->syncCategories($pending);
                $vacancy->unsetRelation('categories');
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

    /**
     * @return list<string>
     */
    public function unversionedAttributes(): array
    {
        // Where a vacancy stands in the list is a decision about the list, not about the text:
        // restoring an older version must not move it.
        return [...$this->contentlessAttributes(), 'position'];
    }

    public function relationKey(): string
    {
        return self::TYPE;
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /** One of the three, and the first of them for anything else. */
    public function setWorkplaceAttribute(mixed $value): void
    {
        $this->attributes['workplace'] = is_string($value) && in_array($value, self::WORKPLACES, true) ? $value : self::ONSITE;
    }

    /** A day, whatever it came as: `2026-11-30`, a moment, a Carbon — or nothing. */
    public function setValidThroughAttribute(mixed $value): void
    {
        $day = Day::from($value);

        $this->attributes['valid_through'] = $day === null ? null : $this->fromDateTime(Carbon::parse($day));
    }

    public function setPostedAtAttribute(mixed $value): void
    {
        $day = Day::from($value);

        $this->attributes['posted_at'] = $day === null ? null : $this->fromDateTime(Carbon::parse($day));
    }

    /** An address in a language when it names a slug in it. */
    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /**
     * Published, not in the bin — and, asked about a language, titled in it: a page with no
     * heading is not a page to show (§4.5). Closed is still visible: the page stays (decision 4).
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
     * Still hiring (decision 13): not closed by hand, and its last day is today or ahead — in the
     * application's timezone, and through `whereDate()`: a date is written as `Y-m-d H:i:s`, and
     * on sqlite `'2026-10-01 00:00:00' < '2026-10-01'` is false.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeOpen(Builder $query, ?string $today = null): Builder
    {
        $today ??= Day::today();
        $through = $this->qualifyColumn('valid_through');

        return $query
            ->where($this->qualifyColumn('is_closed'), false)
            ->where(static function (Builder $open) use ($through, $today): void {
                $open->whereNull($through)->orWhereDate($through, '>=', $today);
            });
    }

    /**
     * Closed by hand, or past its last day — the opposite of {@see scopeOpen()}, to the letter.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeClosed(Builder $query, ?string $today = null): Builder
    {
        $today ??= Day::today();
        $closed = $this->qualifyColumn('is_closed');
        $through = $this->qualifyColumn('valid_through');

        return $query->where(static function (Builder $shut) use ($closed, $through, $today): void {
            $shut->where($closed, true)->orWhere(static function (Builder $expired) use ($through, $today): void {
                $expired->whereNotNull($through)->whereDate($through, '<', $today);
            });
        });
    }

    /**
     * The one order vacancies have.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeByPosition(Builder $query): Builder
    {
        return $query->orderBy($this->qualifyColumn('position'))->orderBy($this->qualifyColumn($this->getKeyName()));
    }

    /** Whether it is closed — the expression of {@see scopeClosed()}, asked of one vacancy. */
    public function isClosed(?string $today = null): bool
    {
        return $this->closedReason($today) !== null;
    }

    /**
     * Why it is closed: by hand wins over expired when both are true — that is the decision
     * somebody took. Null while it is open.
     *
     * @return 'manual'|'expired'|null
     */
    public function closedReason(?string $today = null): ?string
    {
        if ($this->is_closed) {
            return self::CLOSED_MANUAL;
        }

        $through = $this->valid_through;

        return $through !== null && $through->toDateString() < ($today ?? Day::today()) ? self::CLOSED_EXPIRED : null;
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
     * The categories, in the order the editor put them in.
     *
     * @return BelongsToMany<VacancyCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        /** @var BelongsToMany<VacancyCategory, $this> $relation */
        $relation = $this->belongsToCategories(VacancyCategory::class, 'vacancy_category_vacancy', 'vacancy_id', 'category_id');

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
     * @return Collection<int, VacancyCategory>
     */
    public function shownCategories(): Collection
    {
        if ($this->pendingCategories === null) {
            /** @var Collection<int, VacancyCategory> $rows */
            $rows = $this->getRelationValue('categories');

            return $rows;
        }

        $ids = $this->pendingCategories;
        $found = VacancyCategory::query()->whereKey($ids)->get()->keyBy(static fn (VacancyCategory $row): int => (int) $row->getKey());
        $list = [];

        foreach ($ids as $id) {
            $row = $found->get($id);

            if ($row instanceof VacancyCategory) {
                $list[] = $row;
            }
        }

        return new Collection($list);
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
     * The slug of the application form, when one is chosen and switched on — and null without
     * `module-inbox`, whose rows wait for it. Enough for a site to print
     * `<x-webx-inbox::form slug="…">` itself (§4.3).
     */
    public function formSlug(?string $locale = null): ?string
    {
        $form = $this->related(self::FORM, true, $locale)->first();
        $slug = $form?->getAttribute('slug');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * One list in one language (decision 18): the lines in their order, each as written in that
     * language. A line with nothing in it here is left out — no fallback to another language: a
     * list half in a foreign language is worse than one line fewer.
     *
     * @return list<string>
     */
    public function lines(string $field, string $locale): array
    {
        $rows = in_array($field, self::LISTS, true) ? $this->getAttribute($field) : null;
        $lines = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $text = is_array($row) ? self::word($row['text'] ?? null, $locale) : '';

            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return $lines;
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

    /**
     * The name and the lead, for a vacancy nobody wrote an SEO card for. No picture: a vacancy
     * has none of its own, so the site's default one stands.
     */
    public function seoFallback(?string $locale = null): ?SeoData
    {
        $locale ??= app()->getLocale();

        return SeoData::fallback($this->text('title', $locale), $this->text('lead', $locale));
    }

    /** One translated column in one language, with no fallback — '' where it is not written. */
    public function text(string $attribute, string $locale): string
    {
        $value = $this->getTranslation($attribute, $locale, false);

        return is_string($value) ? trim($value) : '';
    }

    /** Whether a place is printed at all: remote work has none (decision 16). */
    public function hasPlace(): bool
    {
        return $this->workplace !== self::REMOTE;
    }

    /** Whether it may be done from anywhere: remote, or hybrid. */
    public function allowsRemote(): bool
    {
        return $this->workplace !== self::ONSITE;
    }

    /**
     * The kinds of employment it names, the ones Google knows, in Google's order.
     *
     * @return list<string>
     */
    public function employment(): array
    {
        $given = is_array($this->employment_types) ? $this->employment_types : [];

        return array_values(array_filter(self::EMPLOYMENT, static fn (string $kind): bool => in_array($kind, $given, true)));
    }

    /**
     * Index → the vacancy. Categories are not steps: they have no pages (decision 2).
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        return Trail::of($locale, new Crumb((string) $this->getTranslation('title', $locale), $this->url($locale)));
    }

    /**
     * `JobPosting`, only for an open vacancy (decision 4) — and only with an organisation to hire
     * for: Google refuses the rest, and a page is better off with no markup than with half of it.
     *
     * @return list<array<string, mixed>>
     */
    public function structuredData(string $locale): array
    {
        if ($this->isClosed()) {
            return [];
        }

        $markup = Container::getInstance()->make(JobPostingMarkup::class)->of($this, $locale);

        return $markup === null ? [] : [$markup];
    }

    private static function word(mixed $value, string $locale): string
    {
        if (is_array($value)) {
            $value = $value[$locale] ?? null;
        }

        return is_string($value) ? trim($value) : '';
    }
}
