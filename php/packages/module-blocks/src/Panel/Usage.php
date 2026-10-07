<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Regions;

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
     * The entities that hold a type: enough to name them in a list and to go and look — which
     * tree it stands in (what the site shows, the draft, or both) and the address, when the
     * entity has one.
     *
     * @return list<array{model: string, id: int|string, title: string|null, published: bool, live: bool, draft: bool, url: string|null, region: string|null}>
     */
    public function of(string $slug): array
    {
        $found = [];

        foreach ($this->entities() as $entity) {
            [$live, $draft] = $this->trees($entity, separately: true);
            $inLive = in_array($slug, Content::types($live), true);
            $inDraft = in_array($slug, Content::types($draft), true);

            if (! $inLive && ! $inDraft) {
                continue;
            }

            $found[] = [
                'model' => $entity::class,
                'id' => $entity->getKey(),
                'title' => $this->title($entity),
                'published' => method_exists($entity, 'isPublished') ? (bool) $entity->isPublished() : true,
                'live' => $inLive,
                'draft' => $inDraft,
                'url' => $this->url($entity),
                // A region is named, not numbered, wherever a tool takes one.
                'region' => $entity instanceof Region ? $entity->name : null,
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
     * Where an entity is on the site, when it has an address of its own; null for one that does
     * not (a region) or whose address cannot be worked out right now.
     */
    private function url(Model $entity): ?string
    {
        if ($entity instanceof Region || ! method_exists($entity, 'url')) {
            return null;
        }

        try {
            $url = $entity->url();
        } catch (Throwable) {
            return null;
        }

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * The live tree and, when the entity keeps one, the draft's. Separately: always both, the
     * missing one empty.
     *
     * @return list<list<array<string, mixed>>>
     */
    private function trees(Model $entity, bool $separately = false): array
    {
        $column = method_exists($entity, 'blocksColumn') ? (string) $entity->blocksColumn() : 'blocks';
        $trees = [];

        $live = $entity->getAttribute($column);

        if (is_array($live)) {
            $trees[0] = array_values(array_filter($live, static fn (mixed $node): bool => Content::isNode($node)));
        }

        if (method_exists($entity, 'draftValues')) {
            $draft = $entity->draftValues();
            $blocks = is_array($draft) ? ($draft[$column] ?? null) : null;

            if (is_array($blocks)) {
                $trees[$separately ? 1 : count($trees)] = array_values(array_filter($blocks, static fn (mixed $node): bool => Content::isNode($node)));
            }
        }

        if ($separately) {
            return [$trees[0] ?? [], $trees[1] ?? []];
        }

        return $trees;
    }

    /**
     * A name for the list. `title` if there is one, in whatever language it is stored in;
     * nothing rather than a guess otherwise.
     */
    private function title(Model $entity): ?string
    {
        // A region has no words of its own; its name in the list is the declared one.
        if ($entity instanceof Region) {
            return (string) __('webx-blocks::regions.usage', ['title' => $this->container->make(Regions::class)->title($entity->name)]);
        }

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
    public function entities(): iterable
    {
        $classes = $this->config->get('webx-blocks.entities', []);
        $classes = is_array($classes) ? $classes : [];

        // The regions always, whether or not the site listed them: they are this package's own
        // entity, and a type standing in the header is checked on the header's values before it
        // is published (§7.4 of the regions spec). Only the declared ones — a row whose name left
        // the config prints nowhere.
        if (! in_array(Region::class, $classes, true)) {
            $declared = array_keys($this->container->make(Regions::class)->declared());

            if ($declared !== []) {
                foreach (Region::query()->whereIn('name', $declared)->cursor() as $region) {
                    yield $region;
                }
            }
        }

        foreach ($classes as $class) {
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
