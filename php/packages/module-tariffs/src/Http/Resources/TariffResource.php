<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Localization\Locales;
use WebxUi\Tariffs\Currencies;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Models\TariffCategory;
use WebxUi\Tariffs\Panel\TariffNames;

/**
 * One tariff as a row of the panel's list (§5.4):
 * `{ id, name, badge, price, currency, symbol, period, price_text, featured, published, position,
 * categories: [{ id, title }], updated_at, deleted_at }`.
 *
 * The words are in the panel's language, else in the default one — the row is how the editor
 * finds the tariff, not what a reader sees. The price stays a number and the symbol travels
 * beside it: the row puts them together the way the panel's language writes a price.
 *
 * @mixin Tariff
 */
final class TariffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tariff $tariff */
        $tariff = $this->resource;

        $locales = app(Locales::class);
        $locale = $locales->current();
        $default = $locales->defaultCode();

        return [
            'id' => (int) $tariff->getKey(),
            'name' => TariffNames::of($tariff, $locales),
            'badge' => $tariff->wordsIn('badge', $locale, $default),
            'price' => $tariff->price === null ? null : (float) $tariff->price,
            'currency' => $tariff->currency,
            'symbol' => app(Currencies::class)->symbol($tariff->currency),
            'period' => $tariff->wordsIn('period', $locale, $default),
            'price_text' => $tariff->wordsIn('price_text', $locale, $default),
            'featured' => $tariff->featured,
            'published' => $tariff->published,
            'position' => (int) $tariff->position,
            'categories' => $tariff->categories
                ->map(static fn (TariffCategory $category): array => [
                    'id' => (int) $category->getKey(),
                    'title' => $category->displayName($locale),
                ])
                ->values()
                ->all(),
            'updated_at' => $tariff->updated_at?->toAtomString(),
            'deleted_at' => $tariff->deleted_at?->toAtomString(),
        ];
    }
}
