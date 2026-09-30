<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Panel;

use Illuminate\Container\Container;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyGroup;
use WebxUi\CatalogProperties\Models\PropertyInterval;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileUrls;

/**
 * How the panel's API spells a property, a value and an interval (§7.3 of the properties spec):
 * translated fields as maps of languages, flags as they are, counts beside them.
 */
final class Resources
{
    /**
     * @return array<string, mixed>
     */
    public static function property(Property $property): array
    {
        $group = $property->relationLoaded('group') ? $property->getRelation('group') : $property->group()->withTrashed()->first();

        return [
            'id' => $property->id,
            'title' => $property->getTranslations('title'),
            'code' => $property->getTranslations('code'),
            'type' => $property->type,
            'group' => $group instanceof PropertyGroup ? ['id' => $group->id, 'title' => $group->getTranslations('title')] : null,
            ...array_combine(Property::FLAGS, array_map(static fn (string $flag): bool => (bool) $property->getAttribute($flag), Property::FLAGS)),
            'unit_prefix' => $property->getTranslations('unit_prefix'),
            'unit_suffix' => $property->getTranslations('unit_suffix'),
            'precision' => $property->precision,
            'filter_mode' => $property->filter_mode,
            'value_order' => $property->value_order,
            'toggle_slug' => $property->getTranslations('toggle_slug'),
            'seo_pattern' => $property->getTranslations('seo_pattern'),
            'position' => $property->position,
            'products_count' => (int) ($property->getAttribute('products_count') ?? $property->affectedProducts()->count()),
            'deleted_at' => $property->deleted_at?->toAtomString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function value(PropertyValue $value): array
    {
        $image = $value->image_id === null ? null : $value->image;

        return [
            'id' => $value->id,
            'parent_id' => $value->parent_id,
            'title' => $value->getTranslations('title'),
            'slug' => $value->getTranslations('slug'),
            'color' => $value->color,
            'image' => $image instanceof MediaFile ? [
                'id' => $image->id,
                'url' => Container::getInstance()->make(FileUrls::class)->url($image),
                'thumb' => Container::getInstance()->make(FileUrls::class)->thumbUrl($image, 160, 160),
            ] : null,
            'depth' => $value->depth,
            'has_children' => $value->rgt - $value->lft > 1,
            'products_count' => (int) ($value->getAttribute('products_count') ?? $value->productCount()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function interval(PropertyInterval $interval): array
    {
        return [
            'id' => $interval->id,
            'title' => $interval->getTranslations('title'),
            'slug' => $interval->getTranslations('slug'),
            'min' => $interval->low(),
            'max' => $interval->high(),
            'position' => $interval->position,
        ];
    }
}
