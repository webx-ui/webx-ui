<?php

declare(strict_types=1);

namespace WebxUi\Themes\Blocks;

use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

/**
 * The block types the chain carries, as one set (spec §9.2, §7.1): every layer's `blocks/*.json`,
 * read bottom up, and a type of an upper layer replaces the lower one with the same slug whole —
 * not field by field, the way a view is replaced.
 *
 * A file that is not a block document is a problem, not a type: it is said by {@see problems()}
 * and left out of the set, so one broken file does not stop the others from syncing.
 */
final class LayerBlocks
{
    /** @var array<string, LayerBlock> */
    private array $set = [];

    /** @var list<LayerBlock> */
    private array $all = [];

    /** @var list<array{file: string, line: int|null, message: string}> */
    private array $problems = [];

    private function __construct() {}

    public static function of(ThemeChain $chain): self
    {
        $blocks = new self;

        foreach (array_reverse($chain->layers) as $layer) {
            foreach ($blocks->read($layer) as $block) {
                $blocks->set[$block->slug] = $block;
                $blocks->all[] = $block;
            }
        }

        ksort($blocks->set, SORT_STRING);

        return $blocks;
    }

    /**
     * The resulting set, by slug.
     *
     * @return array<string, LayerBlock>
     */
    public function set(): array
    {
        return $this->set;
    }

    /**
     * Every type every layer carries, overridden ones too, bottom layer first.
     *
     * @return list<LayerBlock>
     */
    public function all(): array
    {
        return $this->all;
    }

    /**
     * Files of the layers that are not block documents.
     *
     * @return list<array{file: string, line: int|null, message: string}>
     */
    public function problems(): array
    {
        return $this->problems;
    }

    /**
     * Every override of the set whose schema drops or retypes a field of the type below (rule 16):
     * content filled in for the lower type would be lost in the upper one.
     *
     * @return list<array{file: string, line: int|null, message: string}>
     */
    public function incompatible(): array
    {
        $found = [];

        foreach ($this->set as $block) {
            for ($upper = $block; $upper->below !== null; $upper = $upper->below) {
                foreach (SchemaCompatibility::problems($upper->below->schema(), $upper->schema()) as $problem) {
                    $found[] = [
                        'file' => $upper->file,
                        'line' => null,
                        'message' => "{$upper->slug} overrides the type of {$upper->below->layer}: {$problem} — an override may add fields, never drop or retype one",
                    ];
                }
            }
        }

        return $found;
    }

    /**
     * @return list<LayerBlock>
     */
    private function read(ThemeManifest $layer): array
    {
        $files = glob($layer->path.'/blocks/*.json') ?: [];
        sort($files, SORT_STRING);
        $blocks = [];

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);
            $document = json_decode((string) file_get_contents($file), true);

            if (! is_array($document) || array_is_list($document)) {
                $this->problems[] = ['file' => $file, 'line' => null, 'message' => 'not a block document — a layer carries the files webx:blocks:export writes'];

                continue;
            }

            /** @var array<string, mixed> $document */
            $slug = is_string($document['slug'] ?? null) && $document['slug'] !== '' ? $document['slug'] : basename($file, '.json');
            $document['slug'] = $slug;

            $blocks[] = new LayerBlock($slug, $layer->name, $file, $document, $this->set[$slug] ?? null);
        }

        return $blocks;
    }
}
