<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Panel\Exchange;

/**
 * Block types out of the database and into files, one per type (§17).
 *
 * What the site shows is what goes out: the published version. `--draft` takes the version
 * being edited instead — for moving work in progress to another machine, or for a type that
 * was never published. A type with nothing to export is said so, not written empty.
 */
final class ExportCommand extends Command
{
    protected $signature = 'webx:blocks:export
        {slug?* : The types to export; every type when omitted}
        {--path= : The directory to write into; resources/blocks by default}
        {--draft : Export the version being edited rather than the published one}';

    protected $description = 'Write each block type to a JSON file, for git and for another site';

    public function handle(Filesystem $files): int
    {
        $path = $this->option('path');
        $path = is_string($path) && $path !== '' ? rtrim($path, '/\\') : resource_path('blocks');
        $draft = (bool) $this->option('draft');

        /** @var list<string> $slugs */
        $slugs = array_values(array_filter((array) $this->argument('slug'), 'is_string'));

        $query = Block::query()->with(['draftVersion', 'publishedVersion'])->orderBy('slug');

        if ($slugs !== []) {
            $query->whereIn('slug', $slugs);
        }

        $blocks = $query->get();

        $missing = array_diff($slugs, $blocks->pluck('slug')->all());

        if ($missing !== []) {
            $this->components->error('No such block type: '.implode(', ', $missing).'.');

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($path);
        $written = 0;

        foreach ($blocks as $block) {
            $version = $draft ? $block->currentVersion() : $block->publishedVersion;

            if ($version === null) {
                $this->components->warn(sprintf(
                    '%s: skipped — %s.',
                    $block->slug,
                    $draft ? 'no version at all' : 'not published; --draft exports the version being edited',
                ));

                continue;
            }

            $files->put("{$path}/{$block->slug}.json", Exchange::encode(Exchange::document($block, $version)));
            $this->components->twoColumnDetail($block->slug, "{$block->slug}.json · v{$version->number}");
            $written++;
        }

        $this->components->info(sprintf('Exported %d type%s to %s.', $written, $written === 1 ? '' : 's', $path));

        return self::SUCCESS;
    }
}
