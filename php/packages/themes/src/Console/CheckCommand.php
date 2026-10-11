<?php

declare(strict_types=1);

namespace WebxUi\Themes\Console;

use Illuminate\Console\Command;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\Checks\ChainCheck;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\ModuleStyles;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeManifest;

/**
 * Says whether the chain keeps the rules a machine can check (spec §17). Today that is §8 — the
 * stylesheets know only tokens, and tokens.json holds valid values — and §9.2: the block types
 * of the layers know only tokens too, and an override keeps the schema of the type below it. The
 * rest of §17's list joins with the stages that build what it checks.
 *
 * A refusal, not advice: a finding is a line naming the file, the line, what and why, and the
 * exit code is non-zero, so a build or a script can stop on it.
 */
class CheckCommand extends Command
{
    protected $signature = 'webx:theme:check
        {path? : A theme to check instead of the site\'s: a directory with theme.json, or a package directory}';

    protected $description = 'Check that the theme chain knows only tokens: tokens.json, src/css of the layers, CSS of the modules and widgets, block types of the layers';

    public function handle(ThemeChain $chain, BottomLayers $bottom, ThemeLocator $locator, ModuleStyles $modules): int
    {
        try {
            $chain = $this->chain($chain, $locator);
        } catch (ThemeException $failure) {
            $this->components->error($failure->getMessage());

            return self::FAILURE;
        }

        if ($chain->isEmpty()) {
            $this->components->info('No theme is configured (webx-themes.theme), nothing to check.');

            return self::SUCCESS;
        }

        $check = new ChainCheck($chain, $bottom->all(), $modules->modules());
        $findings = $check->run();

        foreach ($findings as $finding) {
            $where = $this->relative($finding['file']).($finding['line'] === null ? '' : ':'.$finding['line']);
            $this->line(trim("  {$where}  {$finding['message']}"));
        }

        $layers = implode(', ', array_map(static fn (ThemeManifest $layer): string => $layer->name, $chain->layers));

        if ($findings !== []) {
            $this->newLine();
            $this->components->error(sprintf('%d %s in the chain %s.', count($findings), count($findings) === 1 ? 'finding' : 'findings', $layers));

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'The chain %s knows only tokens: %d stylesheets, %d block %s and the tokens.json of every layer.',
            $layers,
            count($check->read()),
            $check->blocks(),
            $check->blocks() === 1 ? 'type' : 'types',
        ));

        return self::SUCCESS;
    }

    private function chain(ThemeChain $configured, ThemeLocator $locator): ThemeChain
    {
        $path = $this->argument('path');

        if (! is_string($path) || trim($path) === '') {
            return $configured;
        }

        $directory = rtrim(str_replace('\\', '/', $path), '/');

        if (! preg_match('~^(?:[A-Za-z]:)?/~', $directory)) {
            $directory = str_replace('\\', '/', $this->laravel->basePath($directory));
        }

        // A package directory is not a name Composer knows yet: told to the locator under its own.
        if (is_file($directory.'/composer.json') && ! is_file($directory.'/theme.json')) {
            $manifest = ThemeManifest::fromPackage($directory);
            $locator->register($manifest->name, $directory);

            return ThemeChain::resolve($manifest->name, $locator);
        }

        return ThemeChain::resolve($directory, $locator);
    }

    private function relative(string $file): string
    {
        $base = rtrim(str_replace('\\', '/', $this->laravel->basePath()), '/').'/';

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}
