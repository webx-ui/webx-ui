<?php

declare(strict_types=1);

namespace WebxUi\Admin\SiteModules;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The files of a new site module, from `stubs/local-module/**` (SITE_MODULES §4, §9).
 *
 * The layout of the stubs is the layout of the module; `{{ … }}` in a path or in a file is a name
 * of {@see ModuleName}. Two things are decided here rather than in a stub:
 *
 * - **the languages**: English and every language of the site's panel. A language the stubs carry
 *   is written in it; any other gets the English to translate, so the module's own tests — which
 *   ask for the panel's languages — are green from the first run, and say nothing false;
 * - **the migration's date**, the moment it was first written, and the same name on every run
 *   after that: a second file creating the same table is a deploy that fails.
 *
 * Only the files of the scaffold are ever written. Whatever else is in the folder stays as it is.
 */
final class Scaffold
{
    public const WRITTEN = 'written';

    public const REWRITTEN = 'rewritten';

    public const UNCHANGED = 'unchanged';

    public const ENGLISH = 'written in English, to translate';

    public function __construct(private readonly Filesystem $files) {}

    /** Where the stubs are. */
    public static function stubs(): string
    {
        return dirname(__DIR__, 2).'/stubs/local-module';
    }

    /**
     * Write the module into `$folder`.
     *
     * @param  list<string>  $languages  Besides English: the languages of the site's panel.
     * @return array<string, string> Path inside the folder → what happened to it.
     */
    public function write(ModuleName $name, string $folder, array $languages): array
    {
        $languages = array_values(array_unique(['en', ...$languages]));
        $replace = $name->replacements();
        $migration = $this->migrationName($name, $folder);
        $report = [];

        foreach ($this->stubFiles() as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $target = strtr(substr($relative, 0, -strlen('.stub')), $replace);

            if (str_starts_with($target, 'database/migrations/')) {
                $target = 'database/migrations/'.$migration;
            }

            $language = $this->languageOf($target);

            if ($language !== null && $language !== 'en') {
                // Written once per language of the panel below, from the English when it has to.
                continue;
            }

            $contents = strtr($file->getContents(), $replace);

            if ($language === null) {
                $report[$target] = $this->put($folder.'/'.$target, $contents);

                continue;
            }

            foreach ($languages as $code) {
                $own = $this->stubFor($relative, $code);
                $path = $this->inLanguage($target, $code);
                $status = $this->put($folder.'/'.$path, $own === null ? $contents : strtr($this->files->get($own), $replace));
                $report[$path] = $own === null && $status !== self::UNCHANGED ? self::ENGLISH : $status;
            }
        }

        ksort($report);

        return $report;
    }

    /**
     * Every stub, in every language the stubs carry.
     *
     * @return list<SplFileInfo>
     */
    private function stubFiles(): array
    {
        return array_values($this->files->allFiles(self::stubs(), true));
    }

    /**
     * The migration already written for this table, or a new name of today's moment.
     */
    private function migrationName(ModuleName $name, string $folder): string
    {
        $suffix = 'create_'.$name->table().'_table.php';
        $found = glob($folder.'/database/migrations/*_'.$suffix) ?: [];
        sort($found);

        return $found === [] ? date('Y_m_d_His').'_'.$suffix : basename($found[0]);
    }

    /** `en` for `lang/en/list.php` and `resources/help/en/offices.form.md`; null for anything else. */
    private function languageOf(string $path): ?string
    {
        return preg_match('#^(?:lang|resources/help)/([a-z]{2,3}(?:[_-][A-Za-z]+)?)/#', $path, $match) === 1 ? $match[1] : null;
    }

    private function inLanguage(string $path, string $code): string
    {
        return (string) preg_replace('#^(lang|resources/help)/[^/]+/#', '$1/'.$code.'/', $path);
    }

    /** The stub of this file in that language, when the stubs carry it. */
    private function stubFor(string $relative, string $code): ?string
    {
        $path = self::stubs().'/'.$this->inLanguage($relative, $code);

        return $this->files->exists($path) ? $path : null;
    }

    private function put(string $path, string $contents): string
    {
        if ($this->files->exists($path)) {
            if ($this->files->get($path) === $contents) {
                return self::UNCHANGED;
            }

            $this->files->put($path, $contents);

            return self::REWRITTEN;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);

        return self::WRITTEN;
    }
}
