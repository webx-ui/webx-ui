<?php

declare(strict_types=1);

namespace WebxUi\Press\Models;

use Carbon\CarbonInterface;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Localization\HasTranslations;
use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Press\Seo\OutletMarkup;
use WebxUi\Press\Seo\Trail;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Routing\RouteObserver;
use WebxUi\Routing\RouteSync;
use WebxUi\Routing\RouteTypes;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Contracts\HasSeoFallback;
use WebxUi\Seo\Contracts\HasStructuredData;
use WebxUi\Seo\HasSeo;
use WebxUi\Seo\Rendering\SeoData;

/**
 * An outlet (§4.1 of the press spec): a magazine, a paper, a portal — its logo, its name, a few
 * words about it and its site — with the articles it ran.
 *
 * Seen in a language when it is published, out of the bin, and has at least one article a reader
 * of that language sees (decision 7). The name is not what decides: it is a proper name, taken
 * from whichever language has it ({@see self::displayTitle()}), and hiding an outlet over a
 * formality is what the reviews learnt not to do. The address follows the same rule — an outlet
 * has one only in the languages it is seen in, so the sitemap and the hreflang of its page never
 * name a language where it would answer 404.
 *
 * Without pages (`webx-press.pages = false`) there is no route type for it, and the registry is
 * never asked: {@see self::bootHasUrl()} checks that on every event rather than once, because a
 * test — and `config:cache` on a site — may change it after the model booted.
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $summary
 * @property array<string, mixed>|null $logo
 * @property string|null $website_url
 * @property bool $featured
 * @property bool $published
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Outlet extends Model implements HasBreadcrumbs, HasSeoFallback, HasStructuredData, Visible
{
    use HasExtra;
    use HasSeo;
    use HasTranslations;
    use HasUrl;
    use SoftDeletes;

    /** The screen an outlet is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'press.outlet-form';

    /** The key the address registry knows an outlet by. */
    public const TYPE = 'press-outlet';

    /** The translated columns. */
    public const TRANSLATED = ['title', 'slug', 'summary'];

    /**
     * Outlets whose addresses are waiting for the end of {@see self::holdingAddresses()}.
     *
     * @var array<int, true>|null
     */
    private static ?array $held = null;

    protected $table = 'press_outlets';

    /** @var list<string> */
    protected $fillable = ['title', 'slug', 'summary', 'logo', 'website_url', 'featured', 'published', 'position'];

    /** @var array<string, string> */
    protected $casts = [
        'logo' => 'array',
        'featured' => 'boolean',
        'published' => 'boolean',
        'position' => 'integer',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'featured' => false,
        'published' => false,
    ];

    /**
     * The registry's events, as `HasUrl` would register them — but only ever asked of when the
     * site has pages for outlets (decision 11). Without them there is no route type, and the
     * registry would refuse an entity it has no formatter for.
     */
    public static function bootHasUrl(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::registerModelEvent($event, static function (Outlet $outlet) use ($event): void {
                if ($outlet->hasPages()) {
                    Container::getInstance()->make(RouteObserver::class)->{$event}($outlet);
                }
            });
        }
    }

    protected static function booted(): void
    {
        static::creating(static function (Outlet $outlet): void {
            // At the end of the list: the only place a new outlet can go without moving one
            // somebody else put where it is.
            if (! array_key_exists('position', $outlet->getAttributes())) {
                $outlet->position = (int) static::query()->withTrashed()->max('position') + 1;
            }
        });
    }

    /**
     * Runs the work with the outlets' addresses held back, then works each out once — a form that
     * writes twenty articles is one pass over the registry, not twenty.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public static function holdingAddresses(callable $work): mixed
    {
        if (self::$held !== null) {
            return $work();
        }

        self::$held = [];

        try {
            $result = $work();
            $held = self::heldIds();
        } finally {
            self::$held = null;
        }

        foreach ($held as $id) {
            static::withTrashed()->find($id)?->syncAddresses();
        }

        return $result;
    }

    /**
     * The outlets held back so far — read through a method, since the work that fills the list is
     * a callback PHPStan cannot see into.
     *
     * @return list<int>
     */
    private static function heldIds(): array
    {
        return array_keys(self::$held ?? []);
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return self::TRANSLATED;
    }

    public function extraScreen(): string
    {
        return Outlet::SCREEN;
    }

    /**
     * The articles in their order inside the outlet (decision 6).
     *
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'outlet_id')->orderBy('position')->orderBy('id');
    }

    /** Whether outlets have pages on this site — whether their route type is registered at all. */
    public function hasPages(): bool
    {
        return Container::getInstance()->make(RouteTypes::class)->for($this) !== null;
    }

    /**
     * An address in a language needs a slug in it and something to show in it (decision 7) — not
     * the publication, which is a flag of its own: a draft keeps its address, as a page does, so
     * that publishing does not have to invent one.
     */
    public function hasUrlIn(string $locale): bool
    {
        // Asked of the table rather than of a loaded list: the registry asks in the middle of a
        // save, and a list loaded before it is the articles as they were.
        return $this->hasTranslation('slug', $locale)
            && $this->articles()->visibleIn($locale)->get()->contains(static fn (Article $article): bool => $article->visibleIn($locale));
    }

    /** Whether an article of this outlet is seen in this language, the outlet itself aside. */
    public function hasArticlesIn(string $locale): bool
    {
        if ($this->relationLoaded('articles')) {
            return $this->articles->contains(static fn (Article $article): bool => $article->visibleIn($locale));
        }

        return $this->articles()->visibleIn($locale)->get()->contains(static fn (Article $article): bool => $article->visibleIn($locale));
    }

    /**
     * The languages it has something to show in — the ones it would be seen in once published.
     *
     * @return list<string>
     */
    public function localesWithArticles(): array
    {
        $codes = Container::getInstance()->make(Locales::class)->codes();

        return array_values(array_filter($codes, fn (string $code): bool => $this->hasArticlesIn($code)));
    }

    /**
     * Published, not in the bin — and, asked about a language, with an article seen in it. The
     * handler's 404 and the sitemap's line alike.
     */
    public function isVisible(?string $locale = null): bool
    {
        return $this->published
            && ! $this->trashed()
            && ($locale === null || $this->hasArticlesIn($locale));
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        $query->where($this->qualifyColumn('published'), true);

        if ($locale !== null) {
            $query->whereHas('articles', static fn (Builder $articles): Builder => $articles->scopes(['visibleIn' => [$locale]]));
        }

        return $query;
    }

    /**
     * What a reader of a page in this language may be shown. The query narrows by the key being
     * there; {@see self::isVisible()} is the last word, because a title of spaces is not a title.
     *
     * @param  Builder<Outlet>  $query
     * @return Builder<Outlet>
     */
    public function scopeVisibleIn(Builder $query, string $locale): Builder
    {
        /** @var Builder<Outlet> $query */
        $query = $this->scopeVisible($query, $locale);

        return $query;
    }

    /**
     * @param  Builder<Outlet>  $query
     * @return Builder<Outlet>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy($this->qualifyColumn('position'))->orderBy($this->qualifyColumn('id'));
    }

    /** The outlet's page has nothing of its own to date it by but its last save. */
    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->updated_at;
    }

    /**
     * The name in this language, else in the default one, else in any (decision 7): it is a proper
     * name, and one written in a single language is the name in all of them.
     */
    public function displayTitle(?string $locale = null): string
    {
        $locales = Container::getInstance()->make(Locales::class);
        $order = array_unique([$locale ?? $locales->current(), $locales->defaultCode(), ...$locales->codes()]);
        $written = $this->getTranslations('title');

        foreach ([...$order, ...array_keys($written)] as $code) {
            $title = $written[$code] ?? null;

            if (is_string($title) && trim($title) !== '') {
                return trim($title);
            }
        }

        return '';
    }

    /**
     * The name, the summary and the logo — what the outlet's page shows of it — for an outlet
     * nobody wrote an SEO card for. A logo is a poor social picture, but it is the outlet's own,
     * and it says more about the page than the site's default one does.
     */
    public function seoFallback(?string $locale = null): ?SeoData
    {
        $locale ??= Container::getInstance()->make(Locales::class)->current();

        $logo = $this->logoIn($locale);

        return SeoData::fallback(
            $this->displayTitle($locale),
            $this->text('summary', $locale),
            $logo['url'] ?? null,
            is_string($logo['alt'] ?? null) ? $logo['alt'] : null,
        );
    }

    /** One translated column in one language, with no fallback — '' where it is not written. */
    public function text(string $attribute, string $locale): string
    {
        $value = $this->getTranslation($attribute, $locale, false);

        return is_string($value) ? trim($value) : '';
    }

    /** The site of the outlet, when there is one. */
    public function website(): ?string
    {
        return is_string($this->website_url) && $this->website_url !== '' ? $this->website_url : null;
    }

    /** The library key of the logo, when there is one. */
    public function logoPath(): ?string
    {
        $path = is_array($this->logo) ? ($this->logo['path'] ?? null) : null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * The logo as the library knows it now, or null — also when its file was deleted from the
     * library: a card with an `<img>` that has no address is worse than one with the name.
     *
     * @return array<string, mixed>|null
     */
    public function logoIn(?string $locale = null): ?array
    {
        if ($this->logoPath() === null) {
            return null;
        }

        $logo = Container::getInstance()->make(MediaValues::class)->resolve($this->logo, $locale);

        return is_array($logo) && is_string($logo['url'] ?? null) ? $logo : null;
    }

    /**
     * Its addresses worked out again, when it has any to have: the site has pages for outlets, and
     * this one is not in the bin — the registry forgot it when it went there, and brings it back
     * with the save that restores it.
     */
    public function syncAddresses(): void
    {
        if (self::$held !== null) {
            self::$held[(int) $this->getKey()] = true;

            return;
        }

        if (! $this->hasPages() || $this->trashed()) {
            return;
        }

        // Quietly: the registry is asked right below, and asking it twice is only slower.
        if ($this->fillSlugs()) {
            $this->saveQuietly();
        }

        Container::getInstance()->make(RouteSync::class)->sync($this);
    }

    /**
     * A slug in every language the outlet has something to show in (decision 7). The name is a
     * proper name and so, mostly, is its slug: a language with articles and no slug of its own
     * takes the default language's, else any, else one made from the name — so that an outlet
     * does not have a page in one language only because nobody typed the same word twice, and an
     * agent that adds a Russian article gives it a Russian page.
     *
     * @return bool Whether anything was filled in.
     */
    public function fillSlugs(): bool
    {
        $slugs = array_filter($this->getTranslations('slug'), static fn (mixed $slug): bool => is_string($slug) && $slug !== '');
        $missing = array_values(array_filter(
            Container::getInstance()->make(Locales::class)->codes(),
            fn (string $locale): bool => ! isset($slugs[$locale]) && $this->articles()->visibleIn($locale)->exists(),
        ));

        if ($missing === []) {
            return false;
        }

        $default = Container::getInstance()->make(Locales::class)->defaultCode();
        $given = $slugs[$default] ?? (reset($slugs) ?: Str::slug($this->displayTitle($default)));

        if ($given === '') {
            return false;
        }

        foreach ($missing as $locale) {
            $slugs[$locale] = $given;
        }

        $this->setTranslations('slug', $slugs);

        return true;
    }

    /**
     * The page at the prefix → the outlet (§4.7). The first step is whatever the registry holds at
     * the prefix, and no step at all when nothing there answers.
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        return Trail::of($locale, new Crumb($this->displayTitle($locale), $this->url($locale)));
    }

    /**
     * An `ItemList` of the articles seen on the page, each an `Article` published by this outlet
     * (decision 14).
     *
     * @return list<array<string, mixed>>
     */
    public function structuredData(string $locale): array
    {
        $markup = Container::getInstance()->make(OutletMarkup::class)->of($this, $locale);

        return $markup === null ? [] : [$markup];
    }
}
