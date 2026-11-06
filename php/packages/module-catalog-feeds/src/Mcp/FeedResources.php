<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Mcp;

use WebxUi\CatalogFeeds\Build\FeedFacets;
use WebxUi\CatalogFeeds\Build\FeedLabels;
use WebxUi\CatalogFeeds\Http\FeedMeta;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;

/**
 * What an agent reads before it writes a template (§12 of the feeds spec): it is cheaper to make one
 * right than to read through the refusals of a wrong one.
 */
final class FeedResources
{
    private const RULES = [
        'fields' => '`name` is what the panel calls the feed. `preset` is the key of the preset it started from. `format` is one of '
            .'the formats of catalog_feeds_presets. `locale` is a language of the site, its default when left out. `is_active`: '
            .'a feed switched off is not built.',
        'parts' => 'A file is `head`, then `item` once per product with `separator` between, then `foot`. `item` knows the '
            .'variables of a product; `head` and `foot` know only the frame\'s: catalog://feed-variables has both. An empty '
            .'`separator` means the format\'s own.',
        'mustache' => '{{name}} prints a value, {{#name}}…{{/name}} prints when it is there or goes over a list, '
            .'{{^name}}…{{/name}} prints when it is not. A name holds one dot at most: property.color. {{.}} is refused, so an '
            .'item of a list is always a map: {{#images}}{{url}}{{/images}}. A section reads a value as PHP does: \'0\' is empty.',
        'lists' => 'A separator of a feed does not reach inside a list. Every item of a list has `first` and `last`: '
            .'{{#images}}{{^first}},{{/first}}"{{url}}"{{/images}}.',
        'escaping' => 'A value is escaped once, by the format, and the text of the template never is. xml: entities, and '
            .'characters XML refuses are removed. json: a string\'s escaping without its quotes, the template writes them. csv: '
            .'quoted when it holds the delimiter, a quote or a line break. text: as it is. There is no raw output: {{{x}}} and '
            .'{{&x}} read as {{x}}.',
        'cdata' => 'A variable inside a literal <![CDATA[ … ]]> is refused. HTML goes out through its own variable: '
            .'<description>{{description_cdata}}</description>.',
        'filter' => '`filter` is { "categories": { "include": [12], "exclude": [41] }, "brands": [3], "price": { "min": 10, "max": 5000 }, '
            .'"labels": ["sale"], "properties": { "p.17": ["214"] }, "only_in_stock": false }. Everything is optional; `facets` '
            .'here says what the site has. Categories are ids from catalog_categories_tree, and a branch goes with its children.',
        'selection' => 'A product goes through the filter, then the exclusions of the feed (catalog_feeds_exclusions_set), then '
            .'the check of what the file needs. Only visible products reach a file.',
        'labels' => '`custom_labels` holds up to five sources, by number 0 to 4, each { "type": … }: `none`; `label` with `labels`, '
            .'a list of label codes, of which the first the product has goes out; `price_range` with `ranges`, rising, each with '
            .'`to` and `value`, the last one may have no `to`; `property` with `property`, the code of a property the site has; '
            .'`constant` with `value`. `label_example` here shows each, to fill with the site\'s own codes.',
        'sources' => '`sources.mpn` is { "type": "field" }, the product\'s own, or { "type": "property", "property": "<code>" }.',
        'mappings' => 'catalog_feeds_mappings shows what is mapped. A status with no row goes out by whether it can be bought; '
            .'a category with no row takes after the nearest parent that has one. `stock_map` is an object, status code → the '
            .'marketplace\'s value; catalog_feeds_presets has the statuses. `taxonomy` is the marketplace\'s list of categories, '
            .'`google`, or null for none.',
        'options' => '`options` holds `delimiter`, the delimiter of a csv, a comma when left out; `description`, what '
            .'{{shop.description}} prints; `default_availability`, what every product goes out as on a site without stock '
            .'statuses, in_stock when left out.',
        'schedule' => '`schedule` is `manual`, built only when asked, or `hourly`, `every_6_hours`, `daily`. `schedule_at` is the '
            .'time of a daily one, as 06:30 in the site\'s time zone, 00:00 when left out. `rebuild_on_change` also builds once '
            .'changes to the catalogue have settled.',
        'currency' => '`currency`: a feed is in the site\'s currency, there are no rates to convert by. Another one is refused.',
        'order' => 'catalog_feeds_presets → catalog_feeds_create → catalog_feeds_mappings_set → catalog_feeds_preview → '
            .'catalog_feeds_rebuild → catalog_feeds_builds.',
    ];

    /** One source of each kind, by number: an object, so that it reads as `custom_labels` is sent. */
    private const LABEL_EXAMPLE = [
        0 => ['type' => FeedLabels::LABEL, 'labels' => ['sale', 'new']],
        1 => ['type' => FeedLabels::PRICE_RANGE, 'ranges' => [['to' => 100, 'value' => 'budget'], ['to' => 1000, 'value' => 'middle'], ['value' => 'premium']]],
        2 => ['type' => FeedLabels::PROPERTY, 'property' => 'color'],
        3 => ['type' => FeedLabels::CONSTANT, 'value' => 'autumn'],
        4 => ['type' => FeedLabels::NONE],
    ];

    public function __construct(
        private readonly FeedMeta $meta,
        private readonly FeedFacets $facets,
        private readonly Locales $locales,
    ) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'catalog://feed-variables',
                'Variables of a feed',
                'What a template of a feed may print, by part: `item` for a product, `frame` for the head and the foot.',
                fn (): array => $this->meta->variables(),
            ),
            new McpResource(
                'catalog://feed-rules',
                'How a feed is written',
                'What each field of a feed holds, how a template is built, what Mustache allows here, how values are escaped, the '
                .'shape of the filter and the sources of labels, and the facets the site offers a filter.',
                fn (): array => [
                    'rules' => self::RULES,
                    'label_sources' => FeedLabels::TYPES,
                    'label_example' => (object) self::LABEL_EXAMPLE,
                    'facets' => $this->facets->offered($this->locales->defaultCode()),
                ],
            ),
        ];
    }
}
