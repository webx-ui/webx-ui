<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Closure;

/**
 * The shapes a `wx-data` input can have, by name: `recipes.card` is what a recipe card holds.
 *
 * A module registers the shapes of what it hands to components, from its provider — not from
 * its routes, which a cached site never loads. The fields are what the help under the editor
 * and an agent read to know what `$card['…']` can be; the sample is a real value of that shape,
 * for the stage and the checks. It is a closure because a real value comes from the tables — the
 * first published recipe — and it is only asked for when something is about to be drawn.
 */
final class BlockShapes
{
    /** @var array<string, array{fields: list<array{name: string, type: string, description: string}>, sample: Closure(): mixed}> */
    private array $shapes = [];

    /**
     * @param  list<array{name: string, type: string, description?: string}>  $fields
     * @param  Closure(): mixed  $sample
     */
    public function register(string $name, array $fields, Closure $sample): void
    {
        $this->shapes[$name] = [
            'fields' => array_map(static fn (array $field): array => [
                'name' => $field['name'],
                'type' => $field['type'],
                'description' => $field['description'] ?? '',
            ], array_values($fields)),
            'sample' => $sample,
        ];
    }

    public function has(string $name): bool
    {
        return isset($this->shapes[$name]);
    }

    /**
     * @return list<array{name: string, type: string, description: string}>
     */
    public function fields(string $name): array
    {
        return $this->shapes[$name]['fields'] ?? [];
    }

    /** A value of the shape, or null for a shape nobody registered. */
    public function sample(string $name): mixed
    {
        return isset($this->shapes[$name]) ? ($this->shapes[$name]['sample'])() : null;
    }

    /**
     * The shapes a schema's `wx-data` nodes name, described: `{ "recipes.card": { fields } }`.
     * A shape nobody registered is left out rather than described as empty.
     *
     * @param  list<array<string, mixed>>  $schema
     * @return array<string, array{fields: list<array{name: string, type: string, description: string}>}>
     */
    public function describe(array $schema): array
    {
        $described = [];

        foreach (self::named($schema) as $name) {
            if ($this->has($name)) {
                $described[$name] = ['fields' => $this->fields($name)];
            }
        }

        return $described;
    }

    /**
     * The values of a schema's `wx-data` fields made from their shapes' samples, by field id —
     * what a component is drawn on when nobody wrote it a sample.
     *
     * @param  list<array<string, mixed>>  $schema
     * @return array<string, mixed>
     */
    public function sampleOf(array $schema): array
    {
        $values = [];

        foreach (self::dataNodes($schema) as $node) {
            $shape = $node['props']['shape'] ?? null;

            if (is_string($node['id'] ?? null) && is_string($shape) && $this->has($shape)) {
                $values[$node['id']] = $this->sample($shape);
            }
        }

        return $values;
    }

    /**
     * @param  list<array<string, mixed>>  $schema
     * @return list<string>
     */
    private static function named(array $schema): array
    {
        $names = [];

        foreach (self::dataNodes($schema) as $node) {
            $shape = $node['props']['shape'] ?? null;

            if (is_string($shape) && $shape !== '') {
                $names[] = $shape;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function dataNodes(array $nodes): array
    {
        $found = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['type'] ?? null) === 'wx-data') {
                $found[] = $node;
            }

            if (is_array($node['children'] ?? null)) {
                $found = [...$found, ...self::dataNodes(array_values($node['children']))];
            }
        }

        return $found;
    }
}
