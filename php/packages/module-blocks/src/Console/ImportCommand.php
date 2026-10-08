<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use WebxUi\Blocks\Panel\CallCycle;
use WebxUi\Blocks\Panel\Exchange;
use WebxUi\Blocks\Panel\Importer;

/**
 * Block types back from their files (§17).
 *
 * The work is the panel's import — {@see Importer} — so a file the command takes is a file the
 * section's "Import" takes too: one per type as the command writes them, or a pack the panel
 * exported. `--publish` publishes what passes the checks; a type that fails is reported and left
 * as a draft, and the command says so with its exit code.
 */
final class ImportCommand extends Command
{
    protected $signature = 'webx:blocks:import
        {slug?* : The types to import; every file in the directory when omitted}
        {--path= : The directory to read from; resources/blocks by default}
        {--publish : Publish each imported type that passes the checks}
        {--dry-run : Say what would change and write nothing}';

    protected $description = 'Read block types from their JSON files, writing a version where the content differs';

    public function handle(Filesystem $files, Importer $importer): int
    {
        $path = $this->option('path');
        $path = is_string($path) && $path !== '' ? rtrim($path, '/\\') : resource_path('blocks');
        $dryRun = (bool) $this->option('dry-run');
        $publish = (bool) $this->option('publish');

        if (! $files->isDirectory($path)) {
            $this->components->error("{$path} is not a directory.");

            return self::FAILURE;
        }

        /** @var list<string> $slugs */
        $slugs = array_values(array_filter((array) $this->argument('slug'), 'is_string'));

        $paths = $slugs === []
            ? $files->glob("{$path}/*.json")
            : array_map(static fn (string $slug): string => "{$path}/{$slug}.json", $slugs);

        if ($paths === []) {
            $this->components->warn("No block files in {$path}.");

            return self::SUCCESS;
        }

        sort($paths);
        $failed = false;
        $documents = [];

        foreach ($paths as $file) {
            $name = basename($file);

            if (! $files->exists($file)) {
                $this->components->error("{$name}: no such file.");
                $failed = true;

                continue;
            }

            $read = Exchange::read(json_decode((string) $files->get($file), true), $name);

            if ($read === null) {
                $this->components->error("{$name}: not a block type or a pack of them.");
                $failed = true;

                continue;
            }

            array_push($documents, ...$read);
        }

        try {
            $rows = $importer->import($documents, $dryRun, $publish);
        } catch (CallCycle $cycle) {
            $this->components->error('Nothing imported: '.$cycle->getMessage());

            return self::FAILURE;
        }

        foreach ($rows as $row) {
            if ($row['status'] === Importer::FAILED) {
                $this->components->error("{$row['name']}: {$row['error']}");
                $failed = true;

                continue;
            }

            $detail = $row['status'];

            if ($dryRun) {
                $detail .= $row['writes'] ? ' · would write a version' : '';
            } else {
                $detail .= $row['version'] !== null ? " · v{$row['version']}" : '';
                $detail .= $row['published'] !== null ? " · published v{$row['published']}" : '';
            }

            if ($row['error'] !== null) {
                $this->components->error("{$row['slug']}: {$row['error']}");
                $failed = true;
            }

            $this->components->twoColumnDetail($row['slug'], $detail);
        }

        if ($dryRun) {
            $this->components->info('Dry run: nothing was written.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
