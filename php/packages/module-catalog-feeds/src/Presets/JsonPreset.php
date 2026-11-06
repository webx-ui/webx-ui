<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Presets;

/**
 * A flat JSON file for a one-off request (§9.2 of the feeds spec). The quotes are the template's:
 * the escaping looks after what is inside them, and `price` is a number without any.
 */
final class JsonPreset implements FeedPreset
{
    private const HEAD = '{"generated_at":"{{generated_at}}","currency":"{{feed.currency}}","products":[';

    private const ITEM = <<<'JSON'
        {"id":{{id}},"sku":"{{sku}}","name":"{{name}}","url":"{{url}}","price":{{price}},
        "images":[{{#images}}{{^first}},{{/first}}"{{url}}"{{/images}}],"available":"{{availability}}"}
        JSON;

    private const FOOT = ']}';

    public function key(): string
    {
        return 'json';
    }

    public function name(): string
    {
        return 'JSON';
    }

    public function description(): string
    {
        return (string) __('webx-catalog-feeds::presets.json');
    }

    public function values(): array
    {
        return [
            'format' => 'json',
            'head' => self::HEAD,
            'item' => self::ITEM,
            'foot' => self::FOOT,
            'separator' => ',',
        ];
    }
}
