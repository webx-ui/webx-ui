<?php

declare(strict_types=1);

namespace WebxUi\Seo\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use WebxUi\Localization\HasTranslations;
use WebxUi\Seo\Fields;
use WebxUi\Seo\Panel\SeoRules;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Targets\ForeignHost;
use WebxUi\Seo\Targets\UrlTargets;

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
 * @property string|null $entity_type
 * @property int|null $entity_id
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
 * @property-read Collection<int, SeoFaqItem> $faqItems
 */
class SeoUrl extends Model
{
    use HasTranslations;
    use SeoFields;

    protected $table = 'seo_urls';

    /** Set by {@see bind()} when the saved address was replaced by the target of its redirect. */
    public ?string $redirectedFrom = null;

    protected $fillable = [
        'match_type',
        'pattern',
        'entity_type',
        'entity_id',
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
        return Fields::TRANSLATED;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return $this->seoCasts() + [
            'priority' => 'integer',
            'entity_id' => 'integer',
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

        static::saving(static function (SeoUrl $rule): void {
            if ($rule->isDirty(['match_type', 'pattern']) || ! $rule->exists) {
                $rule->bind();
            }
        });

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * An exact address remembers its entity (§18.2), so a renamed page keeps its rule. An
     * address a redirect catches becomes where it leads — the rule was meant for the page the
     * visitor ends up on — and {@see $redirectedFrom} says so to whoever saved it.
     */
    public function bind(): void
    {
        $this->entity_type = null;
        $this->entity_id = null;

        if ($this->match_type !== UrlMatcher::EXACT || str_contains($this->pattern, '?')) {
            return;
        }

        $targets = app(UrlTargets::class);

        try {
            $binding = $targets->resolve($this->pattern);
        } catch (ForeignHost) {
            // Refused by the request before it gets here; a rule written some other way keeps
            // what it said, unbound, the way rules were before binding existed.
            return;
        }

        if ($binding->redirectedFrom !== null) {
            $this->redirectedFrom = $binding->redirectedFrom;
            $this->pattern = $targets->address($binding->target);
        }

        $this->entity_type = $binding->target->entityType;
        $this->entity_id = $binding->target->entityId;
    }

    /**
     * The FAQ of the page (§18.5), in order. Only an exact rule has one: the same `FAQPage` on
     * every page a mask covers is what search engines ask sites not to do.
     *
     * @return HasMany<SeoFaqItem, $this>
     */
    public function faqItems(): HasMany
    {
        return $this->hasMany(SeoFaqItem::class, 'seo_url_id')->orderBy('position')->orderBy('id');
    }

    /** Would turning this rule into a mask or a pattern leave questions with no page? */
    public function hasFaq(): bool
    {
        return $this->exists && $this->faqItems()->exists();
    }

    /** The address as it is now: the entity's own for a bound rule, the pattern otherwise. */
    public function currentPattern(): string
    {
        return app(UrlTargets::class)->ruleAddress($this->pattern, $this->entity_type, $this->entity_id);
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
}
