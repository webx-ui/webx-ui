<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;

/**
 * A block type at one version, flattened: everything the renderer and the picker need in one
 * value, with no model behind it.
 *
 * This is what the registry caches and hands out — a type that came from the cache and one
 * that came from the tables look the same, and the renderer never has to know which.
 */
final readonly class BlockType
{
    /**
     * @param  list<string>|null  $allow  Types allowed inside; null means not a container.
     * @param  list<string>|null  $allowedIn  Where the block may be placed; null means anywhere.
     * @param  list<array<string, mixed>>  $schema  Screen nodes of `@webx-ui/schema`.
     * @param  array<string, mixed>  $sample
     */
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $description,
        public ?string $icon,
        public string $group,
        public int $sort,
        public ?array $allow,
        public ?array $allowedIn,
        public ?int $maxPerEntity,
        public bool $enabled,
        public int $versionId,
        public int $version,
        public array $schema,
        public string $template,
        public string $styles,
        public ?string $script,
        public array $sample,
    ) {}

    public static function fromModels(Block $block, BlockVersion $version): self
    {
        return new self(
            slug: $block->slug,
            title: $block->title,
            description: $block->description,
            icon: $block->icon,
            group: $block->group,
            sort: $block->sort,
            allow: $block->allow,
            allowedIn: $block->allowed_in,
            maxPerEntity: $block->max_per_entity,
            enabled: $block->is_enabled,
            versionId: $version->id,
            version: $version->number,
            schema: $version->schema ?? [],
            template: $version->template ?? '',
            styles: $version->styles ?? '',
            script: $version->script,
            sample: $version->sample ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            slug: (string) $row['slug'],
            title: (string) $row['title'],
            description: isset($row['description']) ? (string) $row['description'] : null,
            icon: isset($row['icon']) ? (string) $row['icon'] : null,
            group: (string) $row['group'],
            sort: (int) $row['sort'],
            allow: is_array($row['allow'] ?? null) ? array_values($row['allow']) : null,
            allowedIn: is_array($row['allowedIn'] ?? null) ? array_values($row['allowedIn']) : null,
            maxPerEntity: isset($row['maxPerEntity']) ? (int) $row['maxPerEntity'] : null,
            enabled: (bool) $row['enabled'],
            versionId: (int) $row['versionId'],
            version: (int) $row['version'],
            schema: is_array($row['schema'] ?? null) ? array_values($row['schema']) : [],
            template: (string) ($row['template'] ?? ''),
            styles: (string) ($row['styles'] ?? ''),
            script: isset($row['script']) ? (string) $row['script'] : null,
            sample: is_array($row['sample'] ?? null) ? $row['sample'] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'group' => $this->group,
            'sort' => $this->sort,
            'allow' => $this->allow,
            'allowedIn' => $this->allowedIn,
            'maxPerEntity' => $this->maxPerEntity,
            'enabled' => $this->enabled,
            'versionId' => $this->versionId,
            'version' => $this->version,
            'schema' => $this->schema,
            'template' => $this->template,
            'styles' => $this->styles,
            'script' => $this->script,
            'sample' => $this->sample,
        ];
    }

    public function isContainer(): bool
    {
        return $this->allow !== null;
    }

    /** May a block of that type be placed inside this one? */
    public function accepts(string $slug): bool
    {
        return $this->allow !== null && in_array($slug, $this->allow, true);
    }

    /**
     * Every field the schema declares, by id — the variables a template may use.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return self::collect($this->schema, static fn (array $node): bool => true);
    }

    /**
     * The fields that hold nested blocks — the `wx-blocks` nodes, by id.
     *
     * @return list<string>
     */
    public function nestedFields(): array
    {
        return self::collect($this->schema, static fn (array $node): bool => ($node['type'] ?? null) === 'wx-blocks');
    }

    /**
     * The fields that hold a language map rather than one value.
     *
     * Whoever writes a value has to know: a localized field keeps `{ en: …, ru: … }`, and
     * putting a string there is not an edit but a loss of every other language.
     *
     * @return list<string>
     */
    public function localizedFields(): array
    {
        return self::collect($this->schema, static fn (array $node): bool => ($node['localized'] ?? false) === true);
    }

    /**
     * Through `children` too: a screen puts fields inside cards and tabs, and a schema may.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @param  callable(array<string, mixed>): bool  $wanted
     * @return list<string>
     */
    private static function collect(array $nodes, callable $wanted): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            if (is_string($node['id'] ?? null) && $node['id'] !== '' && $wanted($node)) {
                $ids[] = $node['id'];
            }

            if (is_array($node['children'] ?? null)) {
                $ids = [...$ids, ...self::collect(array_values($node['children']), $wanted)];
            }
        }

        return array_values(array_unique($ids));
    }
}
