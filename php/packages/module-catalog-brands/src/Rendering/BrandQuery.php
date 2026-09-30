<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Rendering;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\CatalogBrands\Models\Brand;

/**
 * `brands()` — the brands a template may show, as cards: «popular brands» on the home page, a row
 * of logos in the footer.
 *
 *     brands()->featured()->take(8);
 *     brands()->only([3, 1, 7]);
 *
 * Who is shown is the brand's page's rule: published, out of the bin, with an address in the
 * language. The order is the list's — the one the filter and the page of brands keep. The steps
 * and their rules are {@see RecordQuery}'s.
 *
 * @extends RecordQuery<Brand>
 */
final class BrandQuery extends RecordQuery
{
    /** Only the brands marked «featured». */
    public function featured(): self
    {
        return $this->withStep('featured', true);
    }

    protected function newQuery(string $locale): Builder
    {
        return Brand::query()->visible()->with(['logo', 'routes']);
    }

    protected function narrow(Builder $query, string $locale): void
    {
        if ($this->step('featured', false) === true) {
            $query->where($query->getModel()->qualifyColumn('is_featured'), true);
        }
    }

    protected function shownIn(Model $record, string $locale): bool
    {
        return $record->hasUrlIn($locale);
    }

    /**
     * @param  list<Brand>  $records
     * @return list<array<string, mixed>>
     */
    protected function cards(array $records, string $locale): array
    {
        return array_map(static fn (Brand $brand): array => [
            'id' => (int) $brand->id,
            'name' => $brand->displayName($locale),
            'slug' => (string) $brand->getTranslation('slug', $locale, false),
            'url' => $brand->pageUrl($locale),
            'logo' => $brand->logoUrl(),
            'featured' => $brand->is_featured,
            'description' => $brand->descriptionHtml($locale),
        ], $records);
    }
}
