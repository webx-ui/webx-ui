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
use WebxUi\Seo\HasSeo;

/**
 * An entity with an address, a card and an answer to "is it on the site".
 *
 * The three things the sitemap asks of anything, and nothing a page or an article adds on top:
 * those live in their own modules, which test the map against the real thing.
 *
 * @property int $id
 * @property bool $published
 * @property Carbon|null $updated_at
 */
final class MappedEntity extends Model implements Visible
{
    use HasSeo;
    use HasTranslations;
    use HasUrl;

    protected $table = 'mapped_entities';

    protected $guarded = [];

    /** @return list<string> */
    public function translatable(): array
    {
        return ['slug'];
    }

    public function hasUrlIn(string $locale): bool
    {
        return $this->hasTranslation('slug', $locale);
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
