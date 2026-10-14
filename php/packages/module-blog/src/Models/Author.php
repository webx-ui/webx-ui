<?php

declare(strict_types=1);

namespace WebxUi\Blog\Models;

use Carbon\CarbonInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blog\Seo\Trail;
use WebxUi\Localization\HasTranslations;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileUrls;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Contracts\HasSeoFallback;
use WebxUi\Seo\Contracts\HasStructuredData;
use WebxUi\Seo\HasSeo;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Settings\Contacts\Channel;

/**
 * What the site may say about an administrator who writes (§19): the name a reader sees, a
 * photo, a role, a few lines about them — and, when they want it, a page with all their articles.
 *
 * Not the author of an article: that is still the account (`blog_articles.author_id`, decision
 * 14). A profile is found by `user_id`, so an article knows nothing about it and switching the
 * feature off leaves every article as it was.
 *
 * Open twice or not at all: the site allows author pages (`webx-blog.authors`) and the profile
 * asks for one (`is_public`). {@see self::isOpen()} is that answer, and the page, the link from an
 * article and the sitemap all ask it rather than the two flags.
 *
 * @property int $id
 * @property int $user_id
 * @property array<string, string>|string $title
 * @property array<string, string>|string|null $slug
 * @property array<string, string>|string|null $role
 * @property array<string, string>|string|null $bio
 * @property int|null $photo_id
 * @property array<mixed>|null $socials what is stored; {@see self::socialLinks()} is what is printable
 * @property bool $is_public
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CmsUser|null $user
 * @property-read MediaFile|null $photo
 */
class Author extends Model implements HasBreadcrumbs, HasSeoFallback, HasStructuredData, Visible
{
    use HasSeo;
    use HasTranslations;
    use HasUrl;

    protected $table = 'blog_authors';

    /** The screen a profile is edited on, and the one a project patches its own fields onto. */
    public const SCREEN = 'blog.author-form';

    /** What the page of an author with nothing published says to the index (§19.1, decision 7). */
    public const ROBOTS_EMPTY = 'noindex,follow';

    /** @var list<string> */
    protected $fillable = ['user_id', 'title', 'slug', 'role', 'bio', 'photo_id', 'socials', 'is_public'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug', 'role', 'bio'];
    }

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'socials' => 'array'];
    }

    /** The site allows author pages at all — without it there is no section, no page, no link. */
    public static function enabled(): bool
    {
        return (bool) Container::getInstance()->make(Config::class)->get('webx-blog.authors', false);
    }

    /** The site allows it and the author asked for it: a page, and a link to it from the articles. */
    public function isOpen(): bool
    {
        return self::enabled() && $this->is_public;
    }

    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /**
     * Open, with an address in the language, and something published — the sitemap's question. The
     * page itself answers an open author with nothing written yet too (decision 7), out of the
     * index; this is what keeps it out of the map as well.
     */
    public function isVisible(?string $locale = null): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        if ($locale !== null && ! $this->hasUrlIn($locale)) {
            return false;
        }

        return $this->publishedArticles()->exists();
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        if (! self::enabled()) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('is_public', true)
            ->whereIn('user_id', Article::query()->published()->whereNotNull('author_id')->select('author_id'));
    }

    /** The newest of their articles on the site — when the page last changed for a reader. */
    public function visibleUpdatedAt(): ?CarbonInterface
    {
        /** @var Article|null $latest */
        $latest = $this->publishedArticles()->orderByDesc('published_at')->first();

        $article = $latest?->published_at;

        return $article !== null && $this->updated_at !== null && $this->updated_at->greaterThan($article)
            ? $this->updated_at
            : ($article ?? $this->updated_at);
    }

    /**
     * @return BelongsTo<CmsUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(CmsUser::class, 'user_id');
    }

    /**
     * @return BelongsTo<MediaFile, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'photo_id');
    }

    /**
     * Every article the account wrote, in any state — the panel counts these.
     *
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id', 'user_id');
    }

    /**
     * The ones on the site now: published, the date reached.
     *
     * @return HasMany<Article, $this>
     */
    public function publishedArticles(): HasMany
    {
        return $this->articles()->published();
    }

    public function photoUrl(): ?string
    {
        $photo = $this->photo;

        return $photo instanceof MediaFile
            ? Container::getInstance()->make(FileUrls::class)->url($photo)
            : null;
    }

    public function name(?string $locale = null): string
    {
        $title = $this->getTranslation('title', $locale ?? app()->getLocale());

        return is_string($title) ? $title : '';
    }

    /** The few lines about them, through the allowlist of their field type, as the page prints them. */
    public function bioHtml(?string $locale = null): string
    {
        $stored = $this->getTranslation('bio', $locale ?? app()->getLocale());

        if (! is_string($stored) || trim($stored) === '') {
            return '';
        }

        $type = Container::getInstance()->make(FieldTypes::class)->get('wx-rich-text');
        $resolved = $type === null ? $stored : $type->resolve($stored, [], $locale);

        return is_string($resolved) ? $resolved : $stored;
    }

    /**
     * Their name, the bio as the description and the photo as the picture — and, while nothing of
     * theirs is published, out of the index (decision 7). A card written for the page wins over
     * all of it, `robots` included.
     */
    public function seoFallback(?string $locale = null): ?SeoData
    {
        $name = $this->name($locale);
        $data = SeoData::fallback($name !== '' ? $name : null, $this->bioHtml($locale) ?: null, $this->photoUrl(), $name !== '' ? $name : null);

        return $this->publishedArticles()->exists() ? $data : $data->with(robots: self::ROBOTS_EMPTY);
    }

    /**
     * Feed → the author (§19.3).
     *
     * @return list<Crumb>
     */
    public function breadcrumbs(string $locale): array
    {
        return Trail::of($locale, new Crumb($this->name($locale), $this->url($locale)));
    }

    /**
     * A `ProfilePage` about a `Person` — only what the profile actually says.
     *
     * @return list<array<string, mixed>>
     */
    public function structuredData(string $locale): array
    {
        $url = $this->url($locale);

        return [[
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'url' => $url,
            'mainEntity' => $this->person($locale),
        ]];
    }

    /**
     * The `Person` this profile describes, as a `ProfilePage` names it and as an article names its
     * author — the address only where the page is open.
     *
     * @return array<string, mixed>
     */
    public function person(string $locale): array
    {
        $person = ['@type' => 'Person', 'name' => $this->name($locale)];

        $role = $this->getTranslation('role', $locale);

        if (is_string($role) && trim($role) !== '') {
            $person['jobTitle'] = trim($role);
        }

        $bio = SeoData::fallback(null, $this->bioHtml($locale) ?: null)->description;

        if ($bio !== null && $bio !== '') {
            $person['description'] = $bio;
        }

        $photo = $this->photoUrl();

        if ($photo !== null) {
            $person['image'] = SeoData::fallback(null, null, $photo)->og['image'] ?? $photo;
        }

        // The same person wherever they are named: the page's address with `#person`, so the
        // author of every article and the subject of the page are one node to a reader of the graph.
        if ($this->isOpen() && $this->hasUrlIn($locale)) {
            $url = $this->url($locale);
            $person = ['@type' => 'Person', '@id' => $url.'#person', ...array_diff_key($person, ['@type' => true]), 'url' => $url];
        }

        // Their networks, which is how the page is tied to the same person elsewhere (§19.11).
        $sameAs = array_values(array_map(static fn (array $link): string => $link['url'], $this->socialLinks()));

        if ($sameAs !== []) {
            $person['sameAs'] = $sameAs;
        }

        return $person;
    }

    /**
     * The networks as stored — `{ network, url }` rows a browser opens as a page; anything else a
     * hand or an import left in the column is not a link to print.
     *
     * @return list<array{network: string, url: string}>
     */
    public function socialLinks(): array
    {
        $links = [];

        foreach (is_array($this->socials) ? $this->socials : [] as $row) {
            $network = is_array($row) && is_string($row['network'] ?? null) ? trim($row['network']) : '';
            $url = is_array($row) && is_string($row['url'] ?? null) ? trim($row['url']) : '';

            if ($network !== '' && self::isWebAddress($url)) {
                $links[] = ['network' => $network, 'url' => $url];
            }
        }

        return $links;
    }

    /**
     * The networks as the site's contacts hold theirs — what `<x-webx-socials>` of the widgets
     * draws, an icon each.
     *
     * @return list<Channel>
     */
    public function socialChannels(): array
    {
        return array_map(static fn (array $link): Channel => new Channel($link['network'], $link['url']), $this->socialLinks());
    }

    /** `http://` or `https://` with a host — nothing a reader's browser would run instead. */
    public static function isWebAddress(string $url): bool
    {
        return preg_match('~^https?://~i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
