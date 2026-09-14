<?php

declare(strict_types=1);

namespace WebxUi\Seo\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Localization\HasTranslations;
use WebxUi\Seo\Panel\SeoRules;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Rendering\SeoData;

/**
 * What the site should say about one address, or about every address of one shape.
 *
 * The text is per language, one column per field — see `webx-ui/localization`. The picture is
 * not: there are no per-language images anywhere in the panel yet, and a column that already
 * holds an object would have its keys read as language codes the day one was added.
 *
 * @property int $id
 * @property string $match_type
 * @property string $pattern
 * @property int $priority
 * @property mixed $title
 * @property mixed $h1
 * @property mixed $description
 * @property mixed $keywords
 * @property mixed $og_title
 * @property mixed $og_description
 * @property array<string, mixed>|null $og_image
 * @property string|null $canonical
 * @property string|null $robots
 * @property array<int|string, mixed>|null $json_ld
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeoUrl extends Model
{
    use HasTranslations;

    protected $table = 'seo_urls';

    protected $fillable = [
        'match_type',
        'pattern',
        'priority',
        'title',
        'h1',
        'description',
        'keywords',
        'og_title',
        'og_description',
        'og_image',
        'canonical',
        'robots',
        'json_ld',
        'is_active',
    ];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'h1', 'description', 'keywords', 'og_title', 'og_description'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'og_image' => 'array',
            'json_ld' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // The public side reads the compiled list, not the table. A rule that has just been
        // switched off has to stop working on the next request, not in a day.
        $forget = static function (): void {
            app(SeoRules::class)->forget();
        };

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * @param  Builder<SeoUrl>  $query
     * @return Builder<SeoUrl>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Does this rule cover that address? */
    public function covers(string $url): bool
    {
        return UrlMatcher::covers($this->match_type, $this->pattern, $url);
    }

    /** What the rule contributes to the page, in one language. */
    public function toSeoData(?string $locale = null): SeoData
    {
        $rule = $locale === null ? $this : $this->forLocale($locale);

        $og = [
            'title' => $rule->og_title,
            'description' => $rule->og_description,
            'image' => $this->imageUrl($locale),
        ];

        return SeoData::make([
            'title' => $rule->title,
            'h1' => $rule->h1,
            'description' => $rule->description,
            'keywords' => $rule->keywords,
            'canonical' => $rule->canonical,
            'robots' => $rule->robots,
            'og' => $og,
            'jsonLd' => $rule->json_ld,
        ]);
    }

    /**
     * The address of the picture, worked out by whoever owns `wx-media`.
     *
     * Asked through the field-type registry rather than through the media module directly:
     * SEO does not depend on a library being installed, and a site that stores its pictures
     * somewhere else registers its own type under the same name.
     */
    public function imageUrl(?string $locale = null): ?string
    {
        $stored = $this->og_image;

        if (! is_array($stored) || $stored === []) {
            return null;
        }

        $resolved = app(FieldTypes::class)->get('wx-media')?->resolve($stored, ['type' => 'wx-media'], $locale);
        $url = is_array($resolved) ? ($resolved['url'] ?? null) : null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
