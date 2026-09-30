<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Mcp;

use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyGroup;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;

/**
 * `catalog://properties` (§10 of the properties spec): every live property with its type, its code
 * in each language of the site, units, flags — and, per type, which flags and fields it keeps at
 * all, so an agent does not offer a filter on a text or a unit on a colour.
 *
 * The flags per type are what {@see Property} leaves on when it saves; the rest it puts out.
 */
final class PropertiesResource
{
    /** What each type keeps, and the shape of a product's value of it. */
    private const TYPES = [
        Property::SELECT => [
            'value' => 'an id or a slug of the reference book; a list of them when is_multiple',
            'flags' => ['is_multiple', 'is_tree', 'leaves_only', 'is_filterable', 'is_indexable', 'is_searchable', 'in_card', 'on_page', 'in_list', 'has_color', 'has_image'],
            'fields' => ['value_order', 'seo_pattern'],
            'notes' => 'leaves_only only in a tree; the values are catalog_property_values_*.',
        ],
        Property::NUMBER => [
            'value' => 'a number, without the unit',
            'flags' => ['is_filterable', 'is_indexable', 'is_searchable', 'in_card', 'on_page', 'in_list'],
            'fields' => ['filter_mode', 'precision', 'unit_prefix', 'unit_suffix', 'seo_pattern'],
            'notes' => 'filter_mode is slider or intervals; is_indexable and seo_pattern only with intervals (catalog_properties_update takes them).',
        ],
        Property::TEXT => [
            'value' => 'a text: one language as a string, or { "<locale>": text }',
            'flags' => ['is_searchable', 'in_card', 'on_page', 'in_list'],
            'fields' => [],
            'notes' => 'Never a filter.',
        ],
        Property::BOOL => [
            'value' => 'true for yes; null (or false) for no, which is no value at all',
            'flags' => ['is_filterable', 'is_searchable', 'in_card', 'on_page', 'in_list'],
            'fields' => ['toggle_slug'],
            'notes' => 'toggle_slug is the word of «yes» in an address: wifi_yes.',
        ],
    ];

    public function __construct(
        private readonly Properties $properties,
        private readonly Locales $locales,
    ) {}

    public function resource(): McpResource
    {
        return new McpResource(
            'catalog://properties',
            'Properties of products',
            'Every property: id, type, name and code in each language, group, units, precision and flags; and per type '
            .'which flags and fields it keeps and what a product\'s value of it looks like. A product\'s values are '
            .'"properties.values" in catalog_products_update, only for the set of its main category.',
            fn (): array => $this->read(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $locales = $this->locales->codes();
        $groups = PropertyGroup::query()->get()->mapWithKeys(static fn (PropertyGroup $group): array => [$group->id => $group->getTranslations('title')]);

        return [
            'types' => self::TYPES,
            'properties' => array_values(array_map(fn (Property $property): array => [
                'id' => $property->id,
                'type' => $property->type,
                'title' => $property->getTranslations('title'),
                // The code an address carries in each language, the made-up ones included.
                'code' => array_combine($locales, array_map($property->codeIn(...), $locales)),
                'group' => $property->group_id === null ? null : ['id' => $property->group_id, 'title' => $groups[$property->group_id] ?? null],
                'flags' => array_values(array_filter(Property::FLAGS, static fn (string $flag): bool => (bool) $property->getAttribute($flag))),
                ...$property->isNumber() ? [
                    'unit_prefix' => $property->getTranslations('unit_prefix'),
                    'unit_suffix' => $property->getTranslations('unit_suffix'),
                    'precision' => $property->precision,
                    'filter_mode' => $property->filter_mode,
                ] : [],
                ...$property->isSelect() ? ['value_order' => $property->value_order] : [],
            ], $this->properties->all())),
        ];
    }
}
