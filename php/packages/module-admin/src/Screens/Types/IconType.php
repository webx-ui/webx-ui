<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use WebxUi\Admin\Icons\IconSets;
use WebxUi\Admin\Screens\ChecksNewValues;
use WebxUi\Admin\Screens\Presents;

/**
 * `wx-icon-picker`: a name from an icon set.
 *
 * Without a set of its own the field picks from the panel's, which a project extends in the browser
 * (`registerIcons`), so the server does not know which names exist and checks only that the value
 * is a short string — as it always has.
 *
 * A node may name its set: `props.icons` as a list (with `props.sprite` to draw them), or
 * `props.source` — a set registered in {@see IconSets}, `theme` being the icons of the site's
 * theme. The source is spelled out for the panel when the screen is served ({@see present()}),
 * and a name outside the set is refused, but only when it is being written ({@see ChecksNewValues}):
 * an icon the theme has since dropped is marked in the field, and does not stop the record from
 * being saved until somebody picks another.
 */
final class IconType implements ChecksNewValues, Presents
{
    public function __construct(private readonly IconSets $sets) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    public function newValueProblems(mixed $value, array $node): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $source = $this->source($node);

        if ($source !== null && ! $this->sets->has($source)) {
            return [(string) __('webx-admin::screens.icon-source-unknown', ['source' => $source])];
        }

        $names = $this->names($node);

        if ($names === null || in_array($value, $names, true)) {
            return [];
        }

        return [(string) __('webx-admin::screens.icon-unknown', ['name' => $value, 'names' => implode(', ', $names)])];
    }

    /**
     * The set's names and sprite in place of its source. A source nobody registered becomes an
     * empty set: a grid with nothing in it says the screen is wrong, the panel's own icons would
     * say nothing at all.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function present(array $node): array
    {
        $source = $this->source($node);

        if ($source === null) {
            return $node;
        }

        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        unset($props['source']);

        $set = $this->sets->get($source);
        $props['icons'] = $set === null ? [] : $set->names;

        if ($set?->sprite !== null) {
            $props['sprite'] = $set->sprite;
        }

        $node['props'] = $props;

        return $node;
    }

    /**
     * The names the field picks from, or null when it picks from the panel's set.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>|null
     */
    public function names(array $node): ?array
    {
        $source = $this->source($node);

        if ($source !== null) {
            return $this->sets->get($source)->names ?? [];
        }

        $icons = $node['props']['icons'] ?? null;

        if (! is_array($icons)) {
            return null;
        }

        return array_values(array_filter($icons, is_string(...)));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function source(array $node): ?string
    {
        $source = $node['props']['source'] ?? null;

        return is_string($source) && $source !== '' ? $source : null;
    }
}
