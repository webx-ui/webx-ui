<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakeModuleCommand extends Command
{
    protected $signature = 'webx:make-module {name : Name of the module, e.g. Pages}
                            {--force : Overwrite the class if it already exists}';

    protected $description = 'Create an admin panel module class';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly((string) $this->argument('name'));
        $class = Str::endsWith($name, 'Module') ? $name : $name.'Module';
        $id = Str::kebab(Str::beforeLast($class, 'Module'));

        $namespace = $this->laravel->getNamespace().'Cms\\Modules';
        $path = $this->laravel->path('Cms/Modules/'.$class.'.php');

        if ($files->exists($path) && ! $this->option('force')) {
            $this->components->error("{$class} already exists. Pass --force to overwrite it.");

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $this->render($files, $namespace, $class, $id));

        $this->components->info("Module [{$class}] created.");
        $this->components->bulletList([
            "Register it: app(WebxUi\\Admin\\ModuleRegistry::class)->register(new \\{$namespace}\\{$class}());",
            'Do that from a service provider, so the panel knows about it on every request.',
        ]);

        return self::SUCCESS;
    }

    private function render(Filesystem $files, string $namespace, string $class, string $id): string
    {
        return str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ id }}', '{{ title }}'],
            [rtrim($namespace, '\\'), $class, $id, Str::headline($id)],
            $files->get(__DIR__.'/../../stubs/module.stub'),
        );
    }
}
