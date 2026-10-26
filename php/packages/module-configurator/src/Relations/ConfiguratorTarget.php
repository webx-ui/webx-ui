<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Configurator\Engine\Engine;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Pricing\Texts;
use WebxUi\Localization\Locales;

/**
 * The configurators of the site, for whatever chooses one — the block `configurator` first
 * (`wx-configurator`, §12.1), a link or a menu item later.
 *
 * No permission: the name of a configurator is no secret, and whoever puts one on a page is not
 * somebody who has to be able to build one. A configurator off the site stays chosen and is marked
 * in the picker: the site prints nothing for it until it is published again.
 */
final class ConfiguratorTarget extends RelationTarget
{
    public const KEY = 'configurator';

    public function __construct()
    {
        parent::__construct(
            key: self::KEY,
            model: Configurator::class,
            permission: null,
            label: 'webx-configurator::module.configurator',
        );
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Configurator::query();

        return $query;
    }

    public function visible(Model $record, ?string $locale = null): bool
    {
        return $record instanceof Configurator && $record->liveSchema() !== null;
    }

    /**
     * The row with the key under its own name: `wx-configurator` keeps the key rather than the id,
     * and a picker that read it off the subtitle would break the day the subtitle says more.
     *
     * And what a block may start it from (`wx-configurator-preset`, §12.1): the steps of the
     * published schema with their values, the sizes with their limits, in the language asked for —
     * a handful of configurators, each a few lines, so the preset needs no permission of the section
     * and no request of its own.
     *
     * @return array{id: int, title: string, subtitle: string|null, thumb: string|null, visible: bool, trashed: bool, key?: string, choices?: list<array<string, mixed>>}
     */
    public function row(Model $record, string $locale): array
    {
        $row = parent::row($record, $locale);

        if ($record instanceof Configurator) {
            $row['key'] = $record->key;
            $row['choices'] = self::choices($record->liveSchema() ?? [], $locale, app(Locales::class)->defaultCode());
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<array<string, mixed>>
     */
    public static function choices(array $schema, string $locale, string $default): array
    {
        $choices = [];
        $initial = Engine::map($schema['initial'] ?? null);

        foreach (Engine::steps($schema) as $step) {
            $title = Texts::pick($step['label'] ?? null, $locale, $default) ?: Texts::pick($step['title'] ?? null, $locale, $default);
            $key = (string) $step['key'];

            if (in_array($step['type'] ?? null, Engine::CHOICE_TYPES, true)) {
                $choices[] = [
                    'key' => $key,
                    'kind' => 'choice',
                    'label' => $title ?: $key,
                    'initial' => is_scalar($initial[$key] ?? null) ? (string) $initial[$key] : null,
                    'options' => array_map(static fn (array $option): array => [
                        'value' => (string) $option['value'],
                        'label' => Texts::pick($option['label'] ?? null, $locale, $default) ?: (string) $option['value'],
                    ], Engine::options($step)),
                ];

                continue;
            }

            foreach (Engine::fields($step) as $field) {
                $choices[] = [
                    'key' => (string) $field['key'],
                    'kind' => 'size',
                    'label' => Texts::pick($field['label'] ?? null, $locale, $default) ?: (string) $field['key'],
                    'unit' => Texts::pick($field['unit'] ?? null, $locale, $default),
                    'min' => Engine::number(Engine::float($field['min'] ?? null) ?? 0.0),
                    'max' => Engine::number(Engine::float($field['max'] ?? null) ?? 0.0),
                    'step' => Engine::number(Engine::float($field['step'] ?? null) ?? 1.0),
                    'initial' => Engine::number(Engine::float($initial[(string) $field['key']] ?? null)),
                ];
            }
        }

        return $choices;
    }

    protected function subtitle(Model $record, string $locale): ?string
    {
        return $record instanceof Configurator ? $record->key : null;
    }

    /**
     * By the title, as every target, and by the key — the field names a stored key by asking for
     * exactly it, so the configurator whose key it is comes first.
     *
     * @param  Builder<Model>  $query
     */
    protected function search(Builder $query, string $term, string $locale): void
    {
        $titles = $query->getModel()->newQuery();
        parent::search($titles, $term, $locale);

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $key = $query->getModel()->qualifyColumn('key');

        $query->where(static function (Builder $where) use ($titles, $key, $like): void {
            $where->addNestedWhereQuery($titles->getQuery())->orWhere($key, 'like', $like);
        })->orderByRaw($key.' = ? desc', [$term]);
    }
}
