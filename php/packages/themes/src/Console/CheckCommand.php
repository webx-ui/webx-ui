<?php

declare(strict_types=1);

namespace WebxUi\Themes\Console;

use Illuminate\Console\Command;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\Checks\ChainCheck;
use WebxUi\Themes\Contracts\ThemeCheckPart;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\ModuleStyles;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeFonts;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeManifest;

/**
 * Says whether the chain keeps the rules a machine can check (spec §17). Today that is §8 — the
 * stylesheets know only tokens, and tokens.json holds valid values — and §9.2: the block types
 * of the layers know only tokens too, and an override keeps the schema of the type below it; and
 * §13.1 — the icons of the layers follow the colour of the text, and the templates name only
 * icons the chain has, and `theme_asset()` only files a layer has (§13.2); and the words of the layout
 * have every key of `en` in each language a layer declares (§15.2, rule 26); and the fonts of the
 * layers are their own files with a file for every script the site's languages need (§15.3, rule
 * 27); and the letters take their colours from theme_token(), never var() or a literal (§7.5, rule
 * 25). The rest of §17's list joins with the stages that build what it checks. A package adds a
 * check of its own as a `ThemeCheckPart` — the places of the snippets module, say.
 *
 * A refusal, not advice: a finding is a line naming the file, the line, what and why, and the
 * exit code is non-zero, so a build or a script can stop on it.
 */
class CheckCommand extends Command
{
    protected $signature = 'webx:theme:check
        {path? : A theme to check instead of the site\'s: a directory with theme.json, or a package directory}
        {--locales= : The languages the fonts have to draw, comma-separated, instead of the site\'s}';

    protected $description = 'Check that the theme chain knows only tokens: tokens.json, src/css of the layers, CSS of the modules and widgets, block types of the layers; and that its icons follow the text and exist, and that the assets its templates name exist, that its words have the keys of en in every declared locale, that its fonts cover the scripts of the site\'s languages, and that its letters take their colours from theme_token()';

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

        $locales = $this->locales();
        $check = new ChainCheck($chain, $bottom->all(), $modules->modules(), $locales);
        $findings = $check->run();
        $notes = [];

        foreach ($this->parts($chain) as $line) {
            $entry = ['file' => $line['file'], 'line' => $line['line'], 'message' => $line['message']];

            if ($line['note'] ?? false) {
                $notes[] = $entry;
            } else {
                $findings[] = $entry;
            }
        }

        foreach ($findings as $finding) {
            $where = $this->relative($finding['file']).($finding['line'] === null ? '' : ':'.$finding['line']);
            $this->line(trim("  {$where}  {$finding['message']}"));
        }

        foreach ($notes as $note) {
            $where = $this->relative($note['file']).($note['line'] === null ? '' : ':'.$note['line']);
            $this->line(trim("  {$where}  note: {$note['message']}"));
        }

        $layers = implode(', ', array_map(static fn (ThemeManifest $layer): string => $layer->name, $chain->layers));

        if ($findings !== []) {
            $this->newLine();
            $this->components->error(sprintf('%d %s in the chain %s.', count($findings), count($findings) === 1 ? 'finding' : 'findings', $layers));

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'The chain %s knows only tokens: %d stylesheets, %d block %s and the tokens.json of every layer; %d %s, every one its templates name; %s; %s; %s; %s.',
            $layers,
            count($check->read()),
            $check->blocks(),
            $check->blocks() === 1 ? 'type' : 'types',
            $check->icons(),
            $check->icons() === 1 ? 'icon' : 'icons',
            match ($check->assets()) {
                0 => 'no theme_asset() with a path to look up',
                1 => '1 theme_asset() call, a file of the chain',
                default => $check->assets().' theme_asset() calls, every one a file of the chain',
            },
            $check->locales() === 0
                ? 'no layer declares locales for words of its own'
                : 'words of the layout in '.$check->locales().' '.($check->locales() === 1 ? 'locale' : 'locales').', every one with the keys of en',
            $this->fonts($check, $locales),
            match ($check->letters()) {
                0 => 'no letters to read',
                1 => '1 letter, its colours from theme_token()',
                default => $check->letters().' letters, every colour from theme_token()',
            },
        ));

        return self::SUCCESS;
    }

    /**
     * What the packages that know the engine check on top of it, in the views Blade would read:
     * the site's own first, unless a theme is checked by its path — then only the chain's.
     *
     * @return list<array{file: string, line: int|null, message: string, note?: bool}>
     */
    private function parts(ThemeChain $chain): array
    {
        $path = $this->argument('path');
        $site = (! is_string($path) || trim($path) === '') ? self::siteViews($this->laravel['config']->get('view.paths', [])) : [];
        $viewPaths = [...$site, ...$chain->viewPaths()];

        $lines = [];

        foreach ($this->laravel->tagged(ThemeCheckPart::TAG) as $part) {
            if ($part instanceof ThemeCheckPart) {
                array_push($lines, ...$part->check($chain, $viewPaths));
            }
        }

        return $lines;
    }

    /**
     * The site's own view directories (`view.paths`), the ones Blade reads before any layer.
     *
     * @return list<string>
     */
    private static function siteViews(mixed $paths): array
    {
        return array_values(array_map(
            static fn (string $path): string => str_replace('\\', '/', $path),
            array_filter((array) $paths, static fn (mixed $path): bool => is_string($path) && is_dir($path)),
        ));
    }

    /**
     * The languages the fonts are compared with: `--locales`, or the ones the site publishes in.
     *
     * @return list<string>
     */
    private function locales(): array
    {
        $option = $this->option('locales');

        if (is_string($option) && trim($option) !== '') {
            return array_values(array_filter(array_map(trim(...), explode(',', $option)), static fn (string $code): bool => $code !== ''));
        }

        return ThemeFonts::siteLocales($this->laravel);
    }

    /** @param list<string> $locales */
    private function fonts(ChainCheck $check, array $locales): string
    {
        $families = [];

        foreach ($check->faces() as $face) {
            $families[$face['family']] = [...($families[$face['family']] ?? []), ...array_keys($face['files'])];
        }

        if ($families === []) {
            return 'no fonts of its own — the reader\'s fonts draw every script';
        }

        $said = [];

        foreach ($families as $family => $scripts) {
            $said[] = $family.' in '.implode(', ', array_reverse(array_values(array_unique($scripts))));
        }

        return 'fonts '.implode('; ', $said).', every script of '.($locales === [] ? 'the declared locales' : implode(', ', $locales));
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
