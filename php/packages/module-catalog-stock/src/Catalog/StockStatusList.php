<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Catalog;

use WebxUi\Catalog\Variables\StockStatuses;
use WebxUi\CatalogStock\Models\StockStatus;

/** The statuses in their order, in a language, with what they say about buying. */
final class StockStatusList implements StockStatuses
{
    public function all(string $locale): array
    {
        return StockStatus::query()->orderBy('position')->get()
            ->map(static fn (StockStatus $status): array => [
                'code' => (string) $status->code,
                'title' => (string) $status->getTranslation('title', $locale),
                'is_purchasable' => (bool) $status->is_purchasable,
            ])
            ->all();
    }
}
