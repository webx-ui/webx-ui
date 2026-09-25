<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Filesystem\Filesystem;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\BlockInput;
use WebxUi\Blocks\Panel\CallCycle;
use WebxUi\Blocks\Panel\Graph;
use WebxUi\Blocks\Panel\Publisher;
use WebxUi\Blocks\Panel\PublishFailed;
use WebxUi\Blocks\Panel\Usage;
use WebxUi\Blocks\Rendering\Calls;

/**
 * Block types back from their files (§17).
 *
 * Each file is checked by the rules the panel checks a save with, so a file that imports is a
 * type the panel would have accepted. The row is brought up to date; the content is written
 * as a new version only when it differs from the version being edited — importing the same
 * files twice writes nothing. `--publish` runs the publish checks on what was written and
 * publishes what passes; a type that fails is reported and left as a draft, and the command
 * says so with its exit code.
 *
 * The files are written in the order of the call graph — what is called before what calls it —
 * so that publishing a parent checks it against children that are already there.
 */
final class ImportCommand extends Command
{
    protected $signature = 'webx:blocks:import
        {slug?* : The types to import; every file in the directory when omitted}
        {--path= : The directory to read from; resources/blocks by default}
        {--publish : Publish each imported type that passes the checks}
        {--dry-run : Say what would change and write nothing}';

    protected $description = 'Read block types from their JSON files, writing a version where the content differs';

    public function handle(Filesystem $files, ValidatorFactory $validator, Publisher $publisher, Usage $usage): int
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

        /** @var array<string, array{name: string, document: array<string, mixed>}> $read */
        $read = [];

        foreach ($paths as $file) {
            $name = basename($file);

            if (! $files->exists($file)) {
                $this->components->error("{$name}: no such file.");
                $failed = true;

                continue;
            }

            $document = json_decode((string) $files->get($file), true);

            if (! is_array($document)) {
                $this->components->error("{$name}: not a JSON object.");
                $failed = true;

                continue;
            }

            $slug = is_string($document['slug'] ?? null) ? $document['slug'] : basename($name, '.json');
            $document['slug'] = $slug;
            // A file from before kinds existed holds a block: that is all there was.
            $document['kind'] ??= Block::KIND_BLOCK;

            $read[$slug] = ['name' => $name, 'document' => $document];
        }

        // What is called before what calls it (§3.12 of the components spec): a parent is
        // checked against its children when it is published, and a child still in the file is
        // not a child yet. A circle among the files is refused before anything is written.
        try {
            $order = Graph::order(array_map(
                static fn (array $one): array => Calls::of(is_string($one['document']['template'] ?? null) ? $one['document']['template'] : ''),
                $read,
            ));
        } catch (CallCycle $cycle) {
            $this->components->error('Nothing imported: '.$cycle->getMessage());

            return self::FAILURE;
        }

        $counts = $usage->counts();

        foreach ($order as $slug) {
            ['name' => $name, 'document' => $document] = $read[$slug];

            $block = Block::query()->where('slug', $slug)->with(['draftVersion', 'publishedVersion'])->first();

            $check = $validator->make(
                $document,
                BlockInput::rowRules($block === null, $block?->id) + BlockInput::contentRules(),
                BlockInput::messages(),
            );

            if ($check->fails()) {
                $this->components->error("{$name}: ".implode(' ', $check->errors()->all()));
                $failed = true;

                continue;
            }

            $values = BlockInput::values($document);
            $content = BlockInput::content($document);

            $refusal = $block === null ? null : BlockInput::kindRefusal($block, $values['kind'] ?? null, $counts);

            if ($refusal !== null) {
                $this->components->error("{$name}: {$refusal}");
                $failed = true;

                continue;
            }

            $creating = $block === null;
            $block ??= new Block;
            $block->fill($values);
            $rowChanged = $creating || $block->isDirty();
            $versionChanges = $creating || ($content !== [] && $block->contentDiffers($content));

            $status = match (true) {
                $creating => 'created',
                $rowChanged || $versionChanges => 'updated',
                default => 'unchanged',
            };

            if ($dryRun) {
                $this->components->twoColumnDetail($slug, $status.($versionChanges ? ' · would write a version' : ''));

                continue;
            }

            if ($rowChanged) {
                $block->save();
            }

            $detail = $status;

            if ($versionChanges) {
                $version = $block->saveVersion($content, BlockVersion::SOURCE_IMPORT, null, "Imported from {$name}");
                $detail .= " · v{$version->number}";
            }

            if ($publish && $block->draftVersion !== null) {
                try {
                    $published = $publisher->publish($block);
                    $detail .= " · published v{$published->number}";
                } catch (PublishFailed $refused) {
                    $line = $refused->failure->templateLine !== null ? " (template line {$refused->failure->templateLine})" : '';
                    $this->components->error("{$slug}: not published — {$refused->describe()}{$line}");
                    $failed = true;
                } catch (BlocksException $refused) {
                    $this->components->error("{$slug}: not published — {$refused->getMessage()}");
                    $failed = true;
                }
            }

            $this->components->twoColumnDetail($slug, $detail);
        }

        if ($dryRun) {
            $this->components->info('Dry run: nothing was written.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
