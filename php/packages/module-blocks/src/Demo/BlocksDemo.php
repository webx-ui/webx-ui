<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Demo;

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Demo\DemoWords;
use WebxUi\Admin\Demo\ThemeDemo;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Exceptions\RegionRefused;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Panel\BlockInput;
use WebxUi\Blocks\Panel\RegionWriter;
use WebxUi\Blocks\Regions;
use WebxUi\Blocks\Themes\TypeHash;
use WebxUi\Themes\Blocks\LayerBlocks;
use WebxUi\Themes\ThemeChain;

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
 *
 * A type the theme carries in `blocks/` is not the demo's to seed at all (THEMES §23, TH2.3):
 * `webx:theme:sync` puts it on the site, and a demo copy under the same slug would be a type of
 * the site's own that every later sync refuses to take over (§9.2, step 6). `webx:setup` syncs
 * before the demo runs, so the slug is usually taken by then anyway; this is for the demo run on
 * a site whose theme was never synced.
 */
final class BlocksDemo
{
    /** In this order: `columns` allows `text` inside it, and a type may only allow one that exists. */
    private const TYPES = ['hero', 'text', 'columns'];

    /**
     * The regions the demo fills, each with the type made for it and the values it is placed
     * with. Empty values on purpose: the header takes its name from the settings and the footer
     * its line from the application, so renaming the site renames both. The button's words are
     * in each language the site may speak; {@see DemoWords} keeps the ones it does.
     */
    private const REGIONS = [
        'header' => ['type' => 'demo-header', 'values' => ['button_label' => ['en' => 'Get in touch', 'ru' => 'Связаться с нами', 'pl' => 'Napisz do nas'], 'button_url' => '/contacts']],
        'footer' => ['type' => 'demo-footer', 'values' => []],
    ];

    public function __construct(
        private readonly Filesystem $files,
        private readonly Regions $regions,
        private readonly RegionWriter $writer,
        private readonly BlockOffers $offers,
        private readonly DemoWords $words,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        $carried = self::carried();

        foreach ([...self::TYPES, ...$this->themeTypes()] as $slug) {
            if (in_array($slug, $carried, true)) {
                if (! Block::query()->where('slug', $slug)->exists()) {
                    $ledger->note("The block type {$slug} comes with the theme: php artisan webx:theme:sync puts it on the site.");
                }

                continue;
            }

            $this->type($ledger, $slug);
        }

        $this->offered($ledger);
        $this->regions($ledger);
    }

    /**
     * The offered types the theme's pages stand on — the gallery and logos of `webx-ui/widgets`
     * on the showcase of `theme-default`. `webx:setup` installs them before the demo runs; this
     * is for a site that never did, so that the pages the demo writes are pages it can print.
     * Installed the way the command installs them, and journalled, so `--remove` takes them.
     */
    private function offered(DemoLedger $ledger): void
    {
        $wanted = $this->typesOnThemePages();

        if ($wanted === []) {
            return;
        }

        foreach ($this->offers->documents() as $offer) {
            if (! in_array($offer['slug'], $wanted, true) || $this->offers->present($offer['slug'])) {
                continue;
            }

            try {
                $status = $this->offers->install($offer);
            } catch (BlocksException $refused) {
                $ledger->note("The offered block type {$offer['slug']} was refused: {$refused->getMessage()}");

                continue;
            }

            $block = Block::query()->where('slug', $offer['slug'])->first();

            if ($block instanceof Block) {
                $ledger->created($block, $offer['slug']);
            }

            if ($status === BlockOffers::DRAFT) {
                $ledger->note("The offered block type {$offer['slug']} is left as a draft: it does not render on its sample.");
            }
        }
    }

    /**
     * Every block type the theme's demo pages name, nested blocks and child pages included.
     *
     * @return list<string>
     */
    private function typesOnThemePages(): array
    {
        $directory = ThemeDemo::directory('pages');

        if ($directory === null) {
            return [];
        }

        $types = [];
        $walk = static function (mixed $node) use (&$walk, &$types): void {
            if (! is_array($node)) {
                return;
            }

            if (is_string($node['type'] ?? null) && array_key_exists('values', $node)) {
                $types[$node['type']] = true;
            }

            foreach ($node as $child) {
                $walk($child);
            }
        };

        foreach ($this->files->glob($directory.'/*.json') as $file) {
            $walk(json_decode((string) $this->files->get($file), true));
        }

        return array_keys($types);
    }

    private function type(DemoLedger $ledger, string $slug): void
    {
        // A site that already has a type by this name keeps it: the demo is something to
        // look at, never something that overwrites work.
        if (Block::query()->where('slug', $slug)->exists()) {
            return;
        }

        $document = self::document($slug) ?? throw new RuntimeException("{$slug}.json is not a block document.");

        $block = new Block;
        $block->fill(BlockInput::values($document));
        $block->save();

        $ledger->created($block, $slug);

        $block->saveVersion(BlockInput::content($document), BlockVersion::SOURCE_IMPORT, null, 'Demo content');

        try {
            $block->publish();
        } catch (BlocksException $refused) {
            // `columns` allows `text`: on a site where the theme's text is not synced yet, there is no text to allow.
            $ledger->note("The block type {$slug} is left as a draft: {$refused->getMessage()}");
        }
    }

    /**
     * Whether a type on the site is still exactly what the demo seeded under its slug — nobody's
     * work, so `webx:theme:sync --adopt` may hand it to the theme (THEMES §23, TH2.3).
     */
    public static function untouched(Block $block): bool
    {
        $document = in_array($block->slug, [...self::TYPES, ...array_column(self::REGIONS, 'type')], true) ? self::document($block->slug) : null;

        return $document !== null
            && $block->draft_version_id === null
            && TypeHash::ofPublished($block) === TypeHash::ofDocument($document);
    }

    /**
     * Slugs of the block types the theme layers carry in `blocks/`; none without `webx-ui/themes`.
     *
     * @return list<string>
     */
    private static function carried(): array
    {
        $container = Container::getInstance();

        if (! class_exists(LayerBlocks::class) || ! $container->bound(ThemeChain::class)) {
            return [];
        }

        return array_map('strval', array_keys(LayerBlocks::of($container->make(ThemeChain::class))->set()));
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
            $region = $this->writer->save($name, [['key' => "demo-{$name}", 'type' => $type, 'values' => $this->words->in($values)]], null, null, EntityVersion::SOURCE_IMPORT);
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
     * What the demo seeds under a slug, or null for one it has no file of.
     *
     * @return array<string, mixed>|null
     */
    private static function document(string $slug): ?array
    {
        // The theme's copy of a type wins; the module's own fills in what it leaves out.
        $theme = ThemeDemo::directory('blocks');
        $path = $theme !== null && is_file("{$theme}/{$slug}.json")
            ? "{$theme}/{$slug}.json"
            : __DIR__."/../../resources/demo/{$slug}.json";

        if (! is_file($path)) {
            return null;
        }

        $document = json_decode((string) file_get_contents($path), true);

        /** @var array<string, mixed>|null */
        return is_array($document) ? $document : null;
    }
}
