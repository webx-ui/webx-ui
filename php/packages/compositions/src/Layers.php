<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

use Closure;
use Illuminate\Contracts\Config\Repository;

/**
 * Where the files of the compositions come from (COMPOSITIONS §5): the modules that registered a
 * directory in their provider, the themes of the chain — handed over by the engine of themes —
 * and the `patches` of the site's config. A module whose provider is not loaded adds nothing, and
 * that is exactly the set a web request sees.
 */
final class Layers
{
    /** @var array<string, string> module id to its directory */
    private array $modules = [];

    /** @var array<string, list<string>> module id to the handles it names */
    private array $handles = [];

    /** @var (Closure(): list<array{name: string, dir: string, local: bool}>)|null */
    private ?Closure $themes = null;

    public function __construct(
        private readonly Repository $config,
        private readonly string $basePath,
    ) {}

    /**
     * A module's directory; the module is named after the package directory above
     * `resources/compositions` unless it says otherwise.
     */
    public function module(string $dir, ?string $module = null): void
    {
        $dir = self::normalise($dir);
        $module ??= basename(dirname($dir, 2));

        $this->modules[$module] = $dir;
    }

    /** @param list<string> $handles */
    public function handles(string $module, array $handles): void
    {
        $this->handles[$module] = array_values(array_unique([...($this->handles[$module] ?? []), ...$handles]));
    }

    /** @return array<string, list<string>> */
    public function declaredHandles(): array
    {
        return $this->handles;
    }

    /**
     * The theme chain, bottom first; a closure so the chain is resolved only when compiling.
     *
     * @param  Closure(): list<array{name: string, dir: string, local: bool}>  $themes
     */
    public function themes(Closure $themes): void
    {
        $this->themes = $themes;
    }

    /**
     * The same modules and config over another chain: a theme checked by its path, not the
     * site's own.
     *
     * @param  Closure(): list<array{name: string, dir: string, local: bool}>  $themes
     */
    public function withThemes(Closure $themes): self
    {
        $layers = clone $this;
        $layers->themes = $themes;

        return $layers;
    }

    /**
     * Every layer in the order it applies (§5.2) — modules by id, the themes bottom up, the config
     * last. Which of them holds the base is the builder's to find.
     *
     * @return list<Source>
     */
    public function sources(): array
    {
        $sources = [];
        $modules = $this->modules;
        ksort($modules);

        foreach ($modules as $module => $dir) {
            $sources[] = new Source($module, Source::MODULE, $dir, display: $this->display($dir));
        }

        foreach ($this->themes === null ? [] : ($this->themes)() as $theme) {
            $dir = rtrim(str_replace('\\', '/', $theme['dir']), '/');
            $sources[] = new Source($theme['local'] ? $this->display(dirname($dir)) : $theme['name'], Source::THEME, $dir, display: $this->display($dir), packaged: ! $theme['local']);
        }

        $patches = $this->config->get('webx-compositions.patches', []);
        $sources[] = new Source('config', Source::CONFIG, patches: is_array($patches) ? $patches : [], display: 'config/webx-compositions.php');

        return $sources;
    }

    /** @return array<string, string> handle to composition (§4) */
    public function layouts(): array
    {
        $layouts = $this->config->get('webx-compositions.layouts', []);

        return is_array($layouts) ? array_filter($layouts, static fn (mixed $to, mixed $handle): bool => is_string($to) && is_string($handle), ARRAY_FILTER_USE_BOTH) : [];
    }

    /**
     * What the compiled set depends on besides the files themselves: which layers there are and
     * what the config says. A module that comes or goes changes it.
     */
    public function signature(): string
    {
        $sources = array_map(static fn (Source $source): string => $source->label.'='.($source->dir ?? ''), $this->sources());

        return md5(serialize([Compiler::VERSION, $sources, $this->config->get('webx-compositions.patches'), $this->layouts()]));
    }

    /**
     * A path without `.` and `..` — a provider passes `__DIR__.'/../resources/compositions'`, and
     * the module is named after the directory two levels up, which is `..` until it is resolved.
     * Lexically rather than with realpath(): a symlinked package keeps the path the site sees.
     */
    private static function normalise(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = [];

        foreach (explode('/', $path) as $index => $part) {
            if ($part === '..' && $parts !== [] && end($parts) !== '..' && end($parts) !== '') {
                array_pop($parts);
            } elseif ($part !== '.' && ($part !== '' || $index === 0)) {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }

    private function display(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', $this->basePath), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
