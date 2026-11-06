<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Presets;

/**
 * A flat XML file for a one-off request (§9.2 of the feeds spec). The description keeps its HTML in
 * CDATA, which `description_cdata` writes itself: a template cannot put a variable inside a literal
 * CDATA (§2.4). The currency stands on the root: `feed.*` belongs to the head, not to a product.
 */
final class XmlPreset implements FeedPreset
{
    private const HEAD = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<products currency="{{feed.currency}}">'."\n";

    private const ITEM = <<<'XML'
        <product id="{{id}}">
          <sku>{{sku}}</sku>
          <name>{{name}}</name>
          <url>{{url}}</url>
          <price>{{price}}</price>
          <description>{{description_cdata}}</description>
          {{#images}}<image>{{url}}</image>{{/images}}
        </product>
        XML;

    private const FOOT = "\n</products>\n";

    public function key(): string
    {
        return 'xml';
    }

    public function name(): string
    {
        return 'XML';
    }

    public function description(): string
    {
        return (string) __('webx-catalog-feeds::presets.xml');
    }

    public function values(): array
    {
        return [
            'format' => 'xml',
            'head' => self::HEAD,
            'item' => self::ITEM,
            'foot' => self::FOOT,
            'separator' => "\n",
        ];
    }
}
