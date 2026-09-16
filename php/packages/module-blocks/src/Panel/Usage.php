<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Blocks\Content;

/**
 * Where a block type stands.
 *
 * The package does not know the site's entities — `webx-blocks.entities` names the models
 * that use `HasBlocks`, the same list `--warm` reads — so this walks every row of every one of
 * them and looks at both trees: what is on the site and what is in the draft. A draft counts:
 * the editor who put the block there will publish, and a type that fails on it fails then.
 *
 * Read in one pass rather than per type: the list screen asks about every type at once.
 */
final class Usage
{
    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    /**
     * How many entities hold each type, by slug. A type absent from the map is on none.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach ($this->entities() as $entity) {
            foreach ($this->typesOf($entity) as $slug) {
                $counts[$slug] = ($counts[$slug] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * The entities that hold a type: enough to name them in a list, no more.
     *
     * @return list<array{model: string, id: int|string, title: string|null, published: bool}>
     */
    public function of(string $slug): array
    {
        $found = [];

        foreach ($this->entities() as $entity) {
            if (! in_array($slug, $this->typesOf($entity), true)) {
                continue;
            }

            $found[] = [
                'model' => $entity::class,
                'id' => $entity->getKey(),
                'title' => $this->title($entity),
                'published' => method_exists($entity, 'isPublished') ? (bool) $entity->isPublished() : true,
            ];
        }

        return $found;
    }

    /**
     * The values of every instance of a type, live and draft, with where each came from —
     * what a version has to survive before it is published.
     *
     * @return list<array{values: array<string, mixed>, model: string, id: int|string, title: string|null}>
     */
    public function values(string $slug): array
    {
        $found = [];

        foreach ($this->entities() as $entity) {
            foreach ($this->trees($entity) as $tree) {
                Content::walk($tree, function (array $node) use ($slug, $entity, &$found): void {
                    if ($node['type'] !== $slug) {
                        return;
                    }

                    $found[] = [
                        'values' => is_array($node['values'] ?? null) ? $node['values'] : [],
                        'model' => $entity::class,
                        'id' => $entity->getKey(),
                        'title' => $this->title($entity),
                    ];
                });
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function typesOf(Model $entity): array
    {
        $types = [];

        foreach ($this->trees($entity) as $tree) {
            $types = [...$types, ...Content::types($tree)];
        }

        return array_values(array_unique($types));
    }

    /**
     * The live tree and, when the entity keeps one, the draft's.
     *
     * @return list<list<array<string, mixed>>>
     */
    private function trees(Model $entity): array
    {
        $column = method_exists($entity, 'blocksColumn') ? (string) $entity->blocksColumn() : 'blocks';
        $trees = [];

        $live = $entity->getAttribute($column);

        if (is_array($live)) {
            $trees[] = array_values(array_filter($live, static fn (mixed $node): bool => Content::isNode($node)));
        }

        if (method_exists($entity, 'draftValues')) {
            $draft = $entity->draftValues();
            $blocks = is_array($draft) ? ($draft[$column] ?? null) : null;

            if (is_array($blocks)) {
                $trees[] = array_values(array_filter($blocks, static fn (mixed $node): bool => Content::isNode($node)));
            }
        }

        return $trees;
    }

    /**
     * A name for the list. `title` if there is one, in whatever language it is stored in;
     * nothing rather than a guess otherwise.
     */
    private function title(Model $entity): ?string
    {
        $title = $entity->getAttribute('title');

        if (is_string($title) && $title !== '') {
            return $title;
        }

        // A translated column, read raw: the first language that has a word.
        if (is_array($title)) {
            foreach ($title as $text) {
                if (is_string($text) && $text !== '') {
                    return $text;
                }
            }
        }

        $raw = $entity->getRawOriginal('title');

        if (is_string($raw) && str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                foreach ($decoded as $text) {
                    if (is_string($text) && $text !== '') {
                        return $text;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Every row of every model the site named. A class that is not a model is skipped rather
     * than fatal: a typo in the config should not take the section down.
     *
     * @return iterable<int, Model>
     */
    private function entities(): iterable
    {
        $classes = $this->config->get('webx-blocks.entities', []);

        foreach (is_array($classes) ? $classes : [] as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            $model = $this->container->make($class);

            if (! $model instanceof Model) {
                continue;
            }

            foreach ($model->newQuery()->cursor() as $entity) {
                yield $entity;
            }
        }
    }
}
