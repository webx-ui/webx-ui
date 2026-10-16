<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Illuminate\Contracts\Config\Repository as Config;
use Throwable;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Cache\CacheGroups;

/**
 * The block types the site prints, read once and kept.
 *
 * Every page render asks for its types by slug, so what is cached is the published version of
 * every type, template included — a few kilobytes each — in the `blocks` cache group
 * (WEBX_UI_CACHE.md): a version saved, published or deleted moves the group, and the list is
 * read again. Types come from the tables only: a block written by hand in a site's repository is
 * a legitimate case, but not one v0.1 answers.
 */
final class BlockTypes
{
    public const GROUP = 'blocks';

    /** @var array<string, BlockType>|null slug → the published type, disabled ones included */
    private ?array $loaded = null;

    /** The version of the group the types in memory were built under. */
    private ?int $loadedAt = null;

    public function __construct(
        private readonly CacheGroups $groups,
        private readonly Config $config,
    ) {}

    /**
     * What an editor may add: enabled, published, in `sort` then `slug` order. A component is
     * none of that — it is called by a template, never put in content — so it is left out here
     * and found by {@see find()} like any other type.
     *
     * @return list<BlockType>
     */
    public function all(): array
    {
        return array_values(array_filter(
            $this->load(),
            static fn (BlockType $type): bool => $type->enabled && ! $type->isComponent(),
        ));
    }

    /**
     * The published version of a type, whether or not it is still offered to editors: a
     * disabled block keeps rendering wherever it already stands.
     */
    public function find(string $slug): ?BlockType
    {
        return $this->load()[$slug] ?? null;
    }

    /**
     * The version being edited — what a preview shows under its token — or the published one
     * when there is no draft. Straight from the tables: a draft changes on every keystroke,
     * and only the preview ever asks.
     */
    public function draft(string $slug): ?BlockType
    {
        $block = Block::query()->where('slug', $slug)->with(['draftVersion', 'publishedVersion'])->first();
        $version = $block?->currentVersion();

        return $block instanceof Block && $version instanceof BlockVersion
            ? BlockType::fromModels($block, $version)
            : null;
    }

    /**
     * Throw the kept list away: the `blocks` group moves, here and in everything built on it.
     * A block or a version saved through its model moves the group on its own; this is for a
     * write that went round it — an import, a reorder, a command.
     */
    public function forget(): void
    {
        $this->loaded = null;
        $this->groups->bump(self::GROUP);
    }

    /**
     * @return array<string, BlockType>
     */
    private function load(): array
    {
        $version = $this->enabled() ? $this->groups->version(self::GROUP) : null;

        if ($this->loaded !== null && $this->loadedAt === $version) {
            return $this->loaded;
        }

        $read = static function (): array {
            $rows = [];

            /** @var Block $block */
            foreach (Block::query()->published()->with('publishedVersion')->orderBy('sort')->orderBy('slug')->get() as $block) {
                $version = $block->publishedVersion;

                if ($version instanceof BlockVersion) {
                    $rows[$block->slug] = BlockType::fromModels($block, $version)->toArray();
                }
            }

            return $rows;
        };

        try {
            $rows = $this->enabled()
                ? $this->groups->remember('blocks.types', [self::GROUP], $read, $this->ttl(), localized: false)
                : $read();
        } catch (Throwable) {
            // The tables are not there yet — the package is installed and `migrate` has not
            // run — or a console command is doing something else entirely. A page without its
            // blocks is better than a site that will not answer.
            $rows = [];
        }

        $types = [];

        foreach (is_array($rows) ? $rows : [] as $slug => $row) {
            if (is_array($row)) {
                $types[(string) $slug] = BlockType::fromArray($row);
            }
        }

        $this->loadedAt = $version;

        return $this->loaded = $types;
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('webx-blocks.cache.enabled', true);
    }

    private function ttl(): int
    {
        return (int) $this->config->get('webx-blocks.cache.ttl', 86400);
    }
}
