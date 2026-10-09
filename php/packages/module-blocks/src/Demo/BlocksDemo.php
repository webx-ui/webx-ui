<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Demo\ThemeDemo;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Exceptions\RegionRefused;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Panel\BlockInput;
use WebxUi\Blocks\Panel\RegionWriter;
use WebxUi\Blocks\Regions;

/**
 * Three block types, so that a fresh site has something to build a page out of: a hero, a
 * paragraph of text, and a container that holds other blocks (§9 of the new-site spec). And,
 * on a site whose layout declares them, a header and a footer made of blocks (§9 of the regions
 * spec) — published, so that the page shows at once what a region is for.
 *
 * The fixtures are the files `webx:blocks:export` writes, so what ships here is what the panel
 * would have saved — and a site that wants to keep one of them can export it, edit it and
 * import it back long after the demo is gone.
 *
 * The site's theme may bring its own in `demo/blocks/` (§15.1 of the themes spec): its copy of
 * one of the three wins, and the types it adds are seeded after them — the showcase of
 * `theme-default`, whose pages stand on them.
 */
final class BlocksDemo
{
    /** In this order: `columns` allows `text` inside it, and a type may only allow one that exists. */
    private const TYPES = ['hero', 'text', 'columns'];

    /**
     * The regions the demo fills, each with the type made for it and the values it is placed
     * with. Empty values on purpose: the header takes its name from the settings and the footer
     * its line from the application, so renaming the site renames both.
     */
    private const REGIONS = [
        'header' => ['type' => 'demo-header', 'values' => ['button_label' => 'Get in touch', 'button_url' => '/contacts']],
        'footer' => ['type' => 'demo-footer', 'values' => []],
    ];

    public function __construct(
        private readonly Filesystem $files,
        private readonly Regions $regions,
        private readonly RegionWriter $writer,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        foreach ([...self::TYPES, ...$this->themeTypes()] as $slug) {
            $this->type($ledger, $slug);
        }

        $this->regions($ledger);
    }

    private function type(DemoLedger $ledger, string $slug): void
    {
        // A site that already has a type by this name keeps it: the demo is something to
        // look at, never something that overwrites work.
        if (Block::query()->where('slug', $slug)->exists()) {
            return;
        }

        $document = $this->read($slug);

        $block = new Block;
        $block->fill(BlockInput::values($document));
        $block->save();

        $ledger->created($block, $slug);

        $block->saveVersion(BlockInput::content($document), BlockVersion::SOURCE_IMPORT, null, 'Demo content');
        $block->publish();
    }

    /**
     * A region exists where the layout prints it and the configuration declares it; the demo
     * cannot print a tag, so a site without the declaration gets one line saying so rather than
     * rows in a table nothing reads.
     */
    private function regions(DemoLedger $ledger): void
    {
        if (ThemeDemo::themed()) {
            $ledger->note('The theme draws the header and the footer, so the demo leaves both regions empty.');

            return;
        }

        $declared = array_values(array_filter(array_keys(self::REGIONS), $this->regions->has(...)));

        if ($declared === []) {
            $ledger->note('No header or footer region is declared in webx-blocks.regions, so the demo leaves the layout as it is.');

            return;
        }

        foreach ($declared as $name) {
            $this->region($ledger, $name, self::REGIONS[$name]['type'], self::REGIONS[$name]['values']);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function region(DemoLedger $ledger, string $name, string $type, array $values): void
    {
        // Saved once already — by an editor, or by an earlier demo — is somebody's header.
        if ($this->regions->find($name) instanceof Region) {
            return;
        }

        $this->type($ledger, $type);

        try {
            // Through the writer, as the panel saves and publishes: the region's `allow` and
            // `max`, and the render that refuses a publication in which a block throws.
            $region = $this->writer->save($name, [['key' => "demo-{$name}", 'type' => $type, 'values' => $values]], null, null, EntityVersion::SOURCE_IMPORT);
        } catch (RegionRefused $refused) {
            $ledger->note("The {$name} region did not take the demo block: {$refused->sentence()}");

            return;
        }

        $ledger->created($region, "{$name} region");

        try {
            $this->writer->publish($name, null, EntityVersion::SOURCE_IMPORT);
        } catch (RegionRefused $refused) {
            $ledger->note("The {$name} region is left as a draft: {$refused->sentence()}");
        }

        // A version is a row of its own, and deleting the region leaves it behind.
        $ledger->createdVersionsOf($region);
    }

    /**
     * The types the theme adds to the three, in the order of their file names.
     *
     * @return list<string>
     */
    private function themeTypes(): array
    {
        $directory = ThemeDemo::directory('blocks');

        if ($directory === null) {
            return [];
        }

        $slugs = array_map(static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME), $this->files->glob($directory.'/*.json'));
        sort($slugs);

        return array_values(array_diff($slugs, self::TYPES, array_column(self::REGIONS, 'type')));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $slug): array
    {
        // The theme's copy of a type wins; the module's own fills in what it leaves out.
        $theme = ThemeDemo::directory('blocks');
        $path = $theme !== null && $this->files->exists("{$theme}/{$slug}.json")
            ? "{$theme}/{$slug}.json"
            : __DIR__."/../../resources/demo/{$slug}.json";
        $document = json_decode((string) $this->files->get($path), true);

        if (! is_array($document)) {
            throw new RuntimeException("{$slug}.json is not a block document.");
        }

        return $document;
    }
}
