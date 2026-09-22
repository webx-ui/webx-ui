<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\BlockInput;

/**
 * Three block types, so that a fresh site has something to build a page out of: a hero, a
 * paragraph of text, and a container that holds other blocks (§9 of the new-site spec).
 *
 * The fixtures are the files `webx:blocks:export` writes, so what ships here is what the panel
 * would have saved — and a site that wants to keep one of them can export it, edit it and
 * import it back long after the demo is gone.
 */
final class BlocksDemo
{
    /** In this order: `columns` allows `text` inside it, and a type may only allow one that exists. */
    private const TYPES = ['hero', 'text', 'columns'];

    public function __construct(private readonly Filesystem $files) {}

    public function seed(DemoLedger $ledger): void
    {
        foreach (self::TYPES as $slug) {
            // A site that already has a type by this name keeps it: the demo is something to
            // look at, never something that overwrites work.
            if (Block::query()->where('slug', $slug)->exists()) {
                continue;
            }

            $document = $this->read($slug);

            $block = new Block;
            $block->fill(BlockInput::values($document));
            $block->save();

            $ledger->created($block, $slug);

            $block->saveVersion(BlockInput::content($document), BlockVersion::SOURCE_IMPORT, null, 'Demo content');
            $block->publish();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $slug): array
    {
        $path = __DIR__."/../../resources/demo/{$slug}.json";
        $document = json_decode((string) $this->files->get($path), true);

        if (! is_array($document)) {
            throw new RuntimeException("{$slug}.json is not a block document.");
        }

        return $document;
    }
}
