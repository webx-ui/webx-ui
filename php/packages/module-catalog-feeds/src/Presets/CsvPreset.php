<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Presets;

/**
 * A flat CSV file for a one-off request (§9.2 of the feeds spec). The quotes are the escaping's, so
 * the template has none, and the line break that ends the head keeps the first product off the line
 * of the column names.
 */
final class CsvPreset implements FeedPreset
{
    private const HEAD = "id,sku,name,url,price,availability\n";

    private const ITEM = '{{id}},{{sku}},{{name}},{{url}},{{price}},{{availability}}';

    private const FOOT = "\n";

    public function key(): string
    {
        return 'csv';
    }

    public function name(): string
    {
        return 'CSV';
    }

    public function description(): string
    {
        return (string) __('webx-catalog-feeds::presets.csv');
    }

    public function values(): array
    {
        return [
            'format' => 'csv',
            'head' => self::HEAD,
            'item' => self::ITEM,
            'foot' => self::FOOT,
            'separator' => "\n",
            'options' => ['delimiter' => ','],
        ];
    }
}
