<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use WebxUi\Blocks\Panel\CallCycle;
use WebxUi\Blocks\Panel\Exchange;
use WebxUi\Blocks\Panel\Importer;
use WebxUi\Blocks\Panel\ReplaceRefused;
use WebxUi\Blocks\Panel\TypeDiff;
use WebxUi\Blocks\Themes\LayerSource;
use WebxUi\Themes\ThemeChain;

/**
 * Block types onto the site from their files or from the theme layers (BLOCK_SCHEMAS §4) — the
 * one command that puts a type on a site that is already running, and it is run by a person.
 *
 * The work is the panel's import — {@see Importer} — so a file the command takes is a file the
 * section's "Import" takes too: one per type as `webx:blocks:export` writes them, or a pack the
 * panel exported. A new type is created; a type the site has is left as it is and, when it
 * differs, the lines say how. `--replace=<slug>` writes that one over, as a new version; there is
 * no flag for all of them, on purpose: a deploy that ran one would wipe what the client changed.
 *
 * `--publish` publishes what was created or replaced and passes the checks; a type that fails is
 * reported and left as a draft, and the command says so with its exit code. A type that differs
 * is not a failure.
 */
final class ImportCommand extends Command
{
    protected $signature = 'webx:blocks:import
        {slug?* : The types to import; every one the source carries when omitted}
        {--path= : The directory to read from; resources/blocks by default}
        {--theme : Read the block types of the theme layers instead of a directory}
        {--replace=* : Write these types over with the source\'s version where they differ (repeated or comma-separated)}
        {--publish : Publish each type created or replaced that passes the checks}
        {--dry-run : Say what would change and write nothing}';

    protected $description = 'Put block types on the site from their JSON files or the theme layers; types the site has are kept unless named with --replace';

    public function handle(Filesystem $files, Importer $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $publish = (bool) $this->option('publish');
        $theme = (bool) $this->option('theme');
        $path = $this->option('path');

        if ($theme && is_string($path) && $path !== '') {
            $this->components->error('--theme and --path are two sources: choose one.');

            return self::FAILURE;
        }

        /** @var list<string> $slugs */
        $slugs = array_values(array_filter((array) $this->argument('slug'), 'is_string'));
        $failed = false;

        if ($theme) {
            $read = $this->layers($slugs);

            if ($read === null) {
                return self::FAILURE;
            }

            [$source, $documents] = $read;
        } else {
            $path = is_string($path) && $path !== '' ? rtrim($path, '/\\') : resource_path('blocks');
            $read = $this->files($files, $path, $slugs, $failed);

            if ($read === null) {
                return self::FAILURE;
            }

            $source = $path;
            $documents = $read;
        }

        if ($documents === []) {
            if (! $failed) {
                $this->components->warn($theme ? 'The theme layers carry no block types.' : "No block files in {$source}.");
            }

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        $replace = $this->replace();

        try {
            $rows = $importer->import($documents, $dryRun, $publish, $replace);
        } catch (CallCycle|ReplaceRefused $refused) {
            $this->components->error('Nothing imported: '.$refused->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('  <options=bold>Block types — '.($theme ? 'theme layers: ' : '').$source.'</>');
        $this->newLine();

        $differ = [];

        foreach ($rows as $row) {
            if ($row['status'] === Importer::FAILED) {
                // A file is named: a pack carries many types, and the slug may be the file's own name.
                $this->components->twoColumnDetail($row['slug'], '<fg=red>failed — '.($theme ? '' : "{$row['name']}: ").$row['error'].'</>');
                $failed = true;

                continue;
            }

            $this->components->twoColumnDetail($row['slug'], $this->detail($row, $dryRun));

            if ($row['status'] === Importer::DIFFERS) {
                $differ[] = $row['slug'];
            }

            foreach (TypeDiff::describe($row['diff'] ?? []) as [$what, $how]) {
                $this->line(sprintf('      <fg=gray>%-9s</> %s', $what, $how));
            }

            if ($row['error'] !== null) {
                $this->line("      <fg=red>{$row['error']}</>");
                $failed = true;
            }
        }

        if ($differ !== []) {
            $this->newLine();
            $this->line(sprintf(
                '  %d %s from %s and %s kept. To take the source\'s version, name it:',
                count($differ),
                count($differ) === 1 ? 'type differs' : 'types differ',
                $theme ? 'the layers' : 'their files',
                count($differ) === 1 ? 'was' : 'were',
            ));
            $this->line('    php artisan webx:blocks:import '.($theme ? '--theme ' : ($this->option('path') ? "--path={$source} " : ''))
                .implode(' ', array_map(static fn (string $slug): string => "--replace={$slug}", $differ)));
        }

        if ($dryRun) {
            $this->newLine();
            $this->components->info('Dry run: nothing was written.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{status: string, writes: bool, version: int|null, published: int|null, from: int|null, draft: bool}  $row
     */
    private function detail(array $row, bool $dryRun): string
    {
        if ($row['status'] === Importer::UNCHANGED) {
            return 'unchanged';
        }

        if ($row['status'] === Importer::DIFFERS) {
            return 'differs, kept'.($row['draft'] ? " — a draft is open (v{$row['from']})" : '');
        }

        $created = $row['status'] === Importer::CREATED;

        if ($dryRun) {
            $detail = $created ? 'would create' : 'would replace';

            return $detail.($row['writes'] ? ' · would write a version' : ' · the row only');
        }

        $detail = $created ? 'created' : 'replaced';

        if ($row['version'] !== null) {
            $detail .= $created ? " · v{$row['version']}" : " · v{$row['from']} → v{$row['version']}";
        } elseif (! $created) {
            $detail .= ' · the row only';
        }

        return $detail.($row['published'] !== null ? " · published v{$row['published']}" : '');
    }

    /**
     * `--replace=hero,text` and `--replace=hero --replace=text` alike. Kept as typed: `*` and
     * `all` are for the importer to refuse, not for this to drop.
     *
     * @return list<string>
     */
    private function replace(): array
    {
        $named = [];

        foreach ((array) $this->option('replace') as $value) {
            foreach (explode(',', (string) $value) as $slug) {
                $named[] = trim($slug);
            }
        }

        return array_values(array_unique($named));
    }

    /**
     * @param  list<string>  $slugs
     * @return list<array{name: string, document: array<string, mixed>}>|null
     */
    private function files(Filesystem $files, string $path, array $slugs, bool &$failed): ?array
    {
        if (! $files->isDirectory($path)) {
            $this->components->error("{$path} is not a directory.");

            return null;
        }

        $paths = $slugs === []
            ? $files->glob("{$path}/*.json")
            : array_map(static fn (string $slug): string => "{$path}/{$slug}.json", $slugs);

        sort($paths);
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

        return $documents;
    }

    /**
     * The layers' set, and the names of the layers for the heading, bottom first.
     *
     * @param  list<string>  $slugs
     * @return array{string, list<array{name: string, document: array<string, mixed>, layer?: string, refusal?: string}>}|null
     */
    private function layers(array $slugs): ?array
    {
        if (! class_exists(ThemeChain::class) || ! $this->laravel->bound(ThemeChain::class)) {
            $this->components->error('--theme reads the theme layers, and webx-ui/themes is not installed.');

            return null;
        }

        $chain = $this->laravel->make(ThemeChain::class);

        if ($chain->isEmpty()) {
            $this->components->error('No theme is configured (webx-themes.theme): there are no layers to read.');

            return null;
        }

        $documents = LayerSource::read($chain);

        if ($slugs !== []) {
            $carried = array_map(static fn (array $one): string => (string) $one['document']['slug'], $documents);
            $unknown = array_values(array_diff($slugs, $carried));

            if ($unknown !== []) {
                $this->components->error('The theme layers carry no type '.implode(', ', $unknown).'.');

                return null;
            }

            $documents = array_values(array_filter($documents, static fn (array $one): bool => in_array($one['document']['slug'], $slugs, true)));
        }

        $names = array_map(static fn ($layer): string => $layer->name, array_reverse($chain->layers));

        return [implode(', ', $names), $documents];
    }
}
