<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;
use WebxUi\Admin\Backups\Backups;
use WebxUi\Admin\Snapshots\Manifest;
use WebxUi\Admin\Snapshots\MediaDisk;
use WebxUi\Admin\Snapshots\RestorePlan;
use WebxUi\Admin\Snapshots\Restorer;
use WebxUi\Admin\Snapshots\SnapshotFailed;
use WebxUi\Admin\Snapshots\Snapshotter;
use WebxUi\Admin\Snapshots\UrlRewriter;

/**
 * Replaces this stand's content with an archive's.
 *
 * Everything that can refuse refuses before anything is written — an unknown format, migrations
 * this code has never seen, production without `--force`, a "no" to the question — and the
 * rollback dump is taken before the first row goes. The stand's own tables are never part of it.
 */
class SnapshotRestoreCommand extends Command
{
    protected $signature = 'webx:snapshot:restore
        {archive : The .tar.gz made by webx:snapshot}
        {--all : Replace every table the archive has but sessions, cache, queues and migrations}
        {--with-admins : Replace the panel\'s admins and roles too}
        {--no-media : Leave the uploaded files as they are}
        {--keep-extra : Add and replace files, but delete none that the archive lacks}
        {--force : Allow production, and skip the question with --no-interaction}
        {--no-backup : Do not take a webx:db:backup dump first}';

    protected $description = 'Replace this stand\'s content (database and uploaded files) with a webx:snapshot archive';

    public function handle(Restorer $restorer, Backups $backups, Snapshotter $snapshots): int
    {
        $archive = (string) $this->argument('archive');

        if (! is_file($archive)) {
            $this->components->error("There is no file at [{$archive}].");

            return self::FAILURE;
        }

        $all = (bool) $this->option('all');
        $withAdmins = (bool) $this->option('with-admins');
        $media = ! $this->option('no-media');
        $keepExtra = (bool) $this->option('keep-extra');

        try {
            $manifest = $restorer->manifest($archive);

            if (! $restorer->supported()) {
                throw SnapshotFailed::unsupportedDriver($restorer->driver());
            }

            $plan = $restorer->plan($manifest, $all, $withAdmins, $media, $keepExtra);

            if ($plan->unknownMigrations !== []) {
                throw SnapshotFailed::unknownMigrations($plan->unknownMigrations);
            }
        } catch (SnapshotFailed $failure) {
            $this->components->error($failure->getMessage());

            return self::FAILURE;
        }

        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->components->error('This stand is production. Restoring replaces its content; pass --force if that is what you mean.');

            return self::FAILURE;
        }

        $rewriter = new UrlRewriter($manifest->url(), (string) config('app.url'));
        $this->describe($manifest, $plan, $rewriter, $keepExtra);

        if (! $this->agreed()) {
            return self::FAILURE;
        }

        if (! $this->option('no-backup')) {
            if ($this->call('webx:db:backup') !== self::SUCCESS) {
                $this->components->error('The rollback dump failed, so nothing was restored. Fix the backup or pass --no-backup.');

                return self::FAILURE;
            }

            $this->components->info('Rollback dump: '.($backups->latest()?->path ?? '?'));
        }

        $staging = $snapshots->directory().DIRECTORY_SEPARATOR.'.restore-'.bin2hex(random_bytes(4));

        try {
            $restorer->extract($archive, $manifest, $staging, $plan->media);

            if ($plan->pendingMigrations !== []) {
                $this->call('migrate', ['--force' => true]);
            }

            $imported = $restorer->importDatabase($plan, $staging, $rewriter);
            $this->components->twoColumnDetail('Tables replaced', sprintf('%d, %d rows', count($imported['rows']), array_sum($imported['rows'])));

            foreach ($imported['dropped'] as $table => $columns) {
                $this->components->warn("{$table}: the archive's ".implode(', ', $columns).' no longer '.(count($columns) === 1 ? 'exists' : 'exist').' here and '.(count($columns) === 1 ? 'was' : 'were').' left out.');
            }

            foreach ($imported['preserved'] as $table => $count) {
                $this->components->twoColumnDetail("{$table}: kept from this stand", (string) $count);
            }

            foreach ($restorer->orphans($plan->replace) as $orphan) {
                $this->components->warn(sprintf(
                    '%d %s rows point at %s that no longer exist (%s.%s). Nothing was deleted.',
                    $orphan['count'],
                    $orphan['table'],
                    $orphan['parent'],
                    $orphan['table'],
                    $orphan['column'],
                ));
            }

            if ($plan->media) {
                $files = $restorer->restoreMedia($manifest, $staging, $keepExtra);
                $this->components->twoColumnDetail('Files', sprintf(
                    '%d added, %d replaced, %d unchanged, %d deleted',
                    $files['added'],
                    $files['replaced'],
                    $files['unchanged'],
                    $files['deleted'],
                ));

                if (! file_exists(public_path('storage'))) {
                    try {
                        $this->callSilently('storage:link');
                    } catch (Throwable $failure) {
                        $this->components->warn('storage:link failed: '.$failure->getMessage());
                    }
                }
            }
        } catch (SnapshotFailed $failure) {
            $this->components->error($failure->getMessage());

            return self::FAILURE;
        } finally {
            MediaDisk::deleteDirectory($staging);
        }

        $this->clearCaches($restorer);
        $this->components->info('Restored.');

        return self::SUCCESS;
    }

    private function describe(Manifest $manifest, RestorePlan $plan, UrlRewriter $rewriter, bool $keepExtra): void
    {
        $this->components->info(sprintf('%s · %s · %s', $manifest->site(), $manifest->env(), $manifest->createdAt()));
        $this->components->twoColumnDetail('From', $manifest->url());
        $this->components->twoColumnDetail('Replaces', sprintf('%d tables, %d rows', count($plan->replace), $plan->rows));

        if ($plan->kept !== []) {
            $this->components->twoColumnDetail('In the archive, kept as they are here', implode(', ', $plan->kept));
        }

        if ($plan->untouched !== []) {
            $this->components->twoColumnDetail('Not in the archive, left alone', implode(', ', $plan->untouched));
        }

        if ($plan->missing !== []) {
            $this->components->twoColumnDetail('Not on this stand, skipped', implode(', ', $plan->missing));
        }

        $this->components->twoColumnDetail('Files', $plan->media
            ? sprintf('%d from the archive, %s', $plan->files, $keepExtra ? 'none deleted' : $plan->deletions.' deleted here')
            : 'left as they are');

        if ($rewriter->active()) {
            $this->components->twoColumnDetail('Addresses', $manifest->url().' → '.config('app.url'));
        }

        if ($plan->pendingMigrations !== []) {
            $this->components->warn(count($plan->pendingMigrations).' migrations have not run here yet; they run before the import.');
        }

        if ($plan->newerHere !== []) {
            $this->components->warn(sprintf(
                'The archive is older than this stand by %d migrations. Its rows go into the newer tables column by column; data those migrations reshaped is not reshaped again.',
                count($plan->newerHere),
            ));
        }

        if ($plan->undeclared !== [] && ! $this->option('all')) {
            $this->components->warn('No package declares '.implode(', ', $plan->undeclared).'; left as they are.');
        }
    }

    private function agreed(): bool
    {
        if (! $this->input->isInteractive()) {
            if ($this->option('force')) {
                return true;
            }

            $this->components->error('Without a terminal to ask in, pass --force to restore.');

            return false;
        }

        if ($this->confirm('Replace the content of this stand with the archive\'s?', false)) {
            return true;
        }

        $this->components->info('Nothing was changed.');

        return false;
    }

    /**
     * Every cache that may hold the old content: the application's (settings, menus, block
     * types, addresses, languages are all kept there), the compiled views, then whatever the
     * packages asked for — a sitemap, a search index. One that fails is reported; the content is
     * already in, and a cache is not a reason to say it is not.
     */
    private function clearCaches(Restorer $restorer): void
    {
        $commands = [
            ['command' => 'cache:clear', 'parameters' => []],
            ['command' => 'view:clear', 'parameters' => []],
            ...$restorer->afterRestoreCommands(),
        ];
        $available = Artisan::all();

        foreach ($commands as ['command' => $command, 'parameters' => $parameters]) {
            if (! isset($available[$command])) {
                continue;
            }

            try {
                $this->callSilently($command, $parameters);
                $this->components->twoColumnDetail($command, 'done');
            } catch (Throwable $failure) {
                $this->components->warn("{$command} failed: ".$failure->getMessage());
            }
        }
    }
}
