<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use WebxUi\Admin\ModuleRegistry;

final class InstallCommand extends Command
{
    protected $signature = 'webx:install {--force : Overwrite files that have already been published}';

    protected $description = 'Publish the WebX UI admin configuration and shell view';

    public function handle(ModuleRegistry $registry): int
    {
        $this->callSilently('vendor:publish', [
            '--tag' => 'webx-admin-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->callSilently('vendor:publish', [
            '--tag' => 'webx-admin-views',
            '--force' => (bool) $this->option('force'),
        ]);

        $path = '/'.ltrim((string) config('webx-admin.path'), '/');

        $this->components->info('WebX UI admin published.');
        $this->components->twoColumnDetail('Panel', $path);
        $this->components->twoColumnDetail('Manifest', '/'.ltrim((string) config('webx-admin.api_path'), '/').'/manifest');
        $this->components->twoColumnDetail('Modules registered', (string) $registry->count());

        if ($registry->count() === 0) {
            $this->components->warn('No modules yet — `php artisan webx:make-module Pages` creates one.');
        }

        // Saying this out loud beats finding out from a search engine: nothing here
        // authenticates anyone.
        $this->components->warn("The panel at {$path} is open until an auth module adds its middleware.");

        return self::SUCCESS;
    }
}
