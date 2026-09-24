<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests\Fixtures;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;

/**
 * What stands at the prefix when the index is switched off — a page, on a real site. Here a row
 * of its own, because `module-pages` is not a dependency of this package.
 *
 * @property string $title
 * @property bool $published
 */
class Landing extends Model implements HasBreadcrumbs, Visible
{
    protected $table = 'landings';

    protected $guarded = [];

    public $timestamps = false;

    public function breadcrumbs(string $locale): array
    {
        return [new Crumb($this->title, 'https://example.test/services')];
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
        return null;
    }
}
