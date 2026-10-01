<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Http;

use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogLandings\Models\Landing;

/**
 * A landing as the panel reads it: every language of the texts, the set by ids — and as chips,
 * «Brand: Apple, Dell», «Price: to 50 000», in the language of the panel — the count and the mark.
 */
final class LandingResource
{
    public function __construct(private readonly Facets $facets) {}

    /**
     * @return array<string, mixed>
     */
    public function list(Landing $landing): array
    {
        $locale = app()->getLocale();
        $category = $landing->category_id === null ? null : $landing->category;

        return [
            'id' => (int) $landing->id,
            'category_id' => $landing->category_id,
            'category' => $category instanceof Category ? $category->displayName($locale) : null,
            'name' => $landing->getTranslations('name'),
            'slug' => $landing->getTranslations('slug'),
            'url' => $landing->hasUrlIn($locale) ? $landing->url($locale) : null,
            'filters' => $landing->set()->all(),
            'chips' => $this->chips($landing, $locale),
            'products_count' => $landing->products_count,
            'counted_at' => $landing->counted_at?->toIso8601String(),
            'is_published' => (bool) $landing->is_published,
            'on_category' => (bool) $landing->on_category,
            'position' => (int) $landing->position,
            'attention' => $landing->attention,
            'deleted_at' => $landing->deleted_at?->toIso8601String(),
            'updated_at' => $landing->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function full(Landing $landing): array
    {
        $locale = app()->getLocale();
        $recommended = $landing->recommended()->withTrashed()->get();

        return [
            ...$this->list($landing),
            'h1' => $landing->getTranslations('h1'),
            'text_above' => $landing->getTranslations('text_above'),
            'text_below' => $landing->getTranslations('text_below'),
            'sort' => $landing->sort,
            'recommended' => $recommended->map(static fn (Product $product): int => (int) $product->id)->values()->all(),
            // What the form shows for each id without asking the products again.
            'recommended_items' => $recommended->map(static fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => (string) $product->getTranslation('name', $locale),
                'sku' => $product->sku,
                'deleted' => $product->deleted_at !== null,
            ])->values()->all(),
            'seo' => $landing->seoValue(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, text: string}>
     */
    private function chips(Landing $landing, string $locale): array
    {
        $chips = [];

        foreach ($landing->set()->all() as $key => $choice) {
            $facet = $this->facets->find($key);

            if ($facet === null) {
                continue;
            }

            if ($facet->kind() === FacetKind::Range) {
                $min = $choice['min'] ?? null;
                $max = $choice['max'] ?? null;
                $text = trim(($min === null ? '' : $this->number($min)).' – '.($max === null ? '' : $this->number($max)));
            } else {
                $values = $choice['values'] ?? [];
                $labels = $facet->labels($values, $locale);
                $text = implode(', ', array_map(static fn (string $value): string => (string) ($labels[$value] ?? $value), $values));
            }

            $chips[] = ['key' => $key, 'label' => $facet->label(), 'text' => $text];
        }

        return $chips;
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
