<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Presets;

use WebxUi\CatalogFeeds\Build\FeedLabels;

/**
 * Google Merchant: RSS 2.0 with the `g:` namespace (§9.1 of the feeds spec).
 *
 * Google wants an attribute without a value taken out, so whatever can be empty sits in a section,
 * `long_text` too: the catalogue may have no text for a product, and that is a warning, not a reason
 * to leave it out. Only the main `gtin` goes out (§15, decision 7): additional barcodes may be
 * internal codes, and a check digit cannot tell. `gtins` is there for a copy of the template.
 */
final class GooglePreset implements FeedPreset
{
    private const HEAD = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
          <channel>
            <title>{{shop.name}}</title>
            <link>{{shop.url}}</link>
            <description>{{shop.description}}{{^shop.description}}{{shop.name}}{{/shop.description}}</description>
        XML."\n";

    private const ITEM = <<<'XML'
            <item>
              <g:id>{{id}}</g:id>
              <g:title>{{title}}</g:title>
              {{#long_text}}<g:description>{{long_text}}</g:description>{{/long_text}}
              <g:link>{{url}}</g:link>
              <g:image_link>{{image_link}}</g:image_link>
              {{#additional_images}}<g:additional_image_link>{{url}}</g:additional_image_link>{{/additional_images}}
              <g:availability>{{availability}}</g:availability>
              <g:price>{{list_price_with_currency}}</g:price>
              {{#sale_price}}<g:sale_price>{{sale_price_with_currency}}</g:sale_price>{{/sale_price}}
              <g:condition>new</g:condition>
              {{#brand}}<g:brand>{{brand}}</g:brand>{{/brand}}
              {{#gtin}}<g:gtin>{{gtin}}</g:gtin>{{/gtin}}
              {{#mpn}}<g:mpn>{{mpn}}</g:mpn>{{/mpn}}
              <g:identifier_exists>{{identifier_exists}}</g:identifier_exists>
              {{#google_product_category}}<g:google_product_category>{{google_product_category}}</g:google_product_category>{{/google_product_category}}
              {{#product_type}}<g:product_type>{{product_type}}</g:product_type>{{/product_type}}
              {{#group_id}}<g:item_group_id>{{group_id}}</g:item_group_id>{{/group_id}}
              {{#custom_label_0}}<g:custom_label_0>{{custom_label_0}}</g:custom_label_0>{{/custom_label_0}}
              {{#custom_label_1}}<g:custom_label_1>{{custom_label_1}}</g:custom_label_1>{{/custom_label_1}}
              {{#custom_label_2}}<g:custom_label_2>{{custom_label_2}}</g:custom_label_2>{{/custom_label_2}}
              {{#custom_label_3}}<g:custom_label_3>{{custom_label_3}}</g:custom_label_3>{{/custom_label_3}}
              {{#custom_label_4}}<g:custom_label_4>{{custom_label_4}}</g:custom_label_4>{{/custom_label_4}}
            </item>
        XML;

    private const FOOT = "\n  </channel>\n</rss>\n";

    public function key(): string
    {
        return 'google';
    }

    public function name(): string
    {
        return 'Google Merchant';
    }

    public function description(): string
    {
        return (string) __('webx-catalog-feeds::presets.google');
    }

    public function values(): array
    {
        return [
            'format' => 'xml',
            'head' => self::HEAD,
            'item' => self::ITEM,
            'foot' => self::FOOT,
            'separator' => "\n",
            // Empty: a status goes out by `is_purchasable` until a person says otherwise (§6.1).
            'stock_map' => [],
            'taxonomy' => 'google',
            'custom_labels' => array_fill(0, FeedLabels::COUNT, ['type' => FeedLabels::NONE]),
        ];
    }
}
