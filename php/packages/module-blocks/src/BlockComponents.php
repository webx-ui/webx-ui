<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

/**
 * The places modules call a component from, declared in code (§4 of the components spec).
 *
 * A declaration is a module saying "my views call `recipe-card` here, with this data, and fall
 * back to this partial of mine". It is not a type: nothing is written to the tables until the
 * site presses "Customise", and until then the partial is what prints — so one piece of markup
 * never lives in two places. What a declaration does give the panel is a card to show before
 * anything is customised, the input of the type when it is, and a sample the type is checked on
 * before publishing: calls from a module's views are invisible to the graph, and this is the only
 * check they get.
 */
final class BlockComponents
{
    /** @var array<string, array{slug: string, module: string, fallback: string, title: string, description: string|null, schema: list<array<string, mixed>>}> */
    private array $declared = [];

    public function __construct(private readonly BlockShapes $shapes) {}

    /**
     * @param  list<array<string, mixed>>  $schema  Screen nodes, `wx-data` and `wx-slot` included.
     */
    public function declare(
        string $slug,
        string $module,
        string $fallback,
        string $title,
        ?string $description = null,
        array $schema = [],
    ): void {
        $this->declared[$slug] = [
            'slug' => $slug,
            'module' => $module,
            'fallback' => $fallback,
            'title' => $title,
            'description' => $description,
            'schema' => array_values($schema),
        ];
    }

    public function has(string $slug): bool
    {
        return isset($this->declared[$slug]);
    }

    /**
     * @return array{slug: string, module: string, fallback: string, title: string, description: string|null, schema: list<array<string, mixed>>}|null
     */
    public function get(string $slug): ?array
    {
        return $this->declared[$slug] ?? null;
    }

    /**
     * Every declaration, by slug in the order they were made.
     *
     * @return list<array{slug: string, module: string, fallback: string, title: string, description: string|null, schema: list<array<string, mixed>>}>
     */
    public function all(): array
    {
        return array_values($this->declared);
    }

    /**
     * What the declared place is drawn on: every `wx-data` field filled from its shape.
     *
     * @return array<string, mixed>
     */
    public function sample(string $slug): array
    {
        $declared = $this->get($slug);

        return $declared === null ? [] : $this->shapes->sampleOf($declared['schema']);
    }
}
