<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Throwable;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;

/**
 * The block types the site prints, read once and kept.
 *
 * Every page render asks for its types by slug, so what is cached is the published version of
 * every type, template included — a few kilobytes each — and the list is thrown away when a
 * version is saved, published or deleted. Types come from the tables only: a block written by
 * hand in a site's repository is a legitimate case, but not one v0.1 answers.
 */
final class BlockTypes
{
    /** @var array<string, BlockType>|null slug → the published type, disabled ones included */
    private ?array $loaded = null;

    public function __construct(
        private readonly Cache $cache,
        private readonly Config $config,
    ) {}

    /**
     * What an editor may add: enabled, published, in `sort` then `slug` order.
     *
     * @return list<BlockType>
     */
    public function all(): array
    {
        return array_values(array_filter($this->load(), static fn (BlockType $type): bool => $type->enabled));
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

    public function forget(): void
    {
        $this->loaded = null;

        if ($this->enabled()) {
            $this->cache->forget($this->key());
        }
    }

    /**
     * @return array<string, BlockType>
     */
    private function load(): array
    {
        if ($this->loaded !== null) {
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
                ? $this->cache->remember($this->key(), $this->ttl(), $read)
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

    private function key(): string
    {
        return (string) $this->config->get('webx-blocks.cache.key', 'webx.blocks.types');
    }
}
