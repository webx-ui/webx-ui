<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests\Fixtures;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use WebxUi\Localization\HasTranslations;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Contracts\HasStructuredData;
use WebxUi\Seo\HasSeo;

/**
 * An entity that implements every contract of §17.2, the way a content module would: a section
 * above it without an address of its own, then itself; one schema.org block about itself.
 *
 * @property int $id
 * @property bool $published
 * @property Carbon|null $updated_at
 */
final class CrumbedEntity extends Model implements HasBreadcrumbs, HasStructuredData, Visible
{
    use HasSeo;
    use HasTranslations;
    use HasUrl;

    protected $table = 'crumbed_entities';

    protected $guarded = [];

    /** @return list<string> */
    public function translatable(): array
    {
        return ['title', 'slug'];
    }

    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
    }

    /** @return list<Crumb> */
    public function breadcrumbs(string $locale): array
    {
        return [
            new Crumb('Section'),
            new Crumb((string) $this->getTranslation('title', $locale), $this->url($locale)),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function structuredData(string $locale): array
    {
        return [['@context' => 'https://schema.org', '@type' => 'Thing', 'name' => (string) $this->getTranslation('title', $locale)]];
    }

    public function isVisible(?string $locale = null): bool
    {
        return (bool) $this->published;
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        return $query->where('published', true);
    }

    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->updated_at;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }
}
