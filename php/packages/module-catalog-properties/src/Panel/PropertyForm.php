<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Panel;

use Illuminate\Support\Facades\DB;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\Localization\Locales;

/**
 * The values of the property's screen — read for the editor and written back (§7.3 of the
 * properties spec): the property's own columns, and the fields a project patched onto the screen,
 * which go into `extra`.
 *
 * Only what travelled is written, as the product's form does: a field left out keeps its value, and
 * a translated one is laid over what is there language by language — the form sends every
 * language, an agent the ones it has.
 */
final class PropertyForm
{
    public const OWN = [
        'title', 'code', 'type', 'group_id', ...Property::FLAGS, 'filter_mode', 'value_order',
        'unit_prefix', 'unit_suffix', 'precision', 'toggle_slug', 'seo_pattern',
    ];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly Locales $locales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function read(Property $property): array
    {
        $values = [];

        foreach (self::OWN as $field) {
            $values[$field] = $property->isTranslatableAttribute($field) ? $property->getTranslations($field) : $property->getAttribute($field);
        }

        foreach ((array) ($property->extraRaw() ?? []) as $name => $value) {
            $values[(string) $name] ??= $value;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     */
    public function save(Property $property, array $input, ?callable $can = null): Property
    {
        $split = $this->record->split(Property::SCREEN, $input, self::OWN, [], $can);

        DB::transaction(function () use ($property, $split): void {
            foreach ($split->own as $field => $value) {
                if ($property->isTranslatableAttribute($field)) {
                    if (is_array($value)) {
                        $property->setTranslations($field, [...$property->getTranslations($field), ...$value]);
                    } else {
                        $property->setTranslation($field, $this->locales->current(), $value);
                    }

                    continue;
                }

                $property->setAttribute($field, match (true) {
                    in_array($field, Property::FLAGS, true) => (bool) $value,
                    $field === 'group_id' => $value === null || $value === '' ? null : (int) $value,
                    $field === 'precision' => (int) ($value ?? 0),
                    default => $value,
                });
            }

            if ($split->extra !== []) {
                $property->setAttribute('extra', $this->record->merge(Property::SCREEN, $property->extraRaw(), $split->extra));
            }

            $property->save();
        });

        return $property->refresh();
    }
}
