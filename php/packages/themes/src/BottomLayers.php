<?php

declare(strict_types=1);

namespace WebxUi\Themes;

/**
 * Packages at the bottom of the chain that are not themes but ship built files the same way —
 * `webx-ui/widgets` (spec §7.1, §11). They have no views or tokens of the chain to resolve; what
 * they share with a packaged theme is `dist/`, published to `public/themes/<name>/` by
 * `webx:theme:sync` with the same hashed directories, so a link to them is cached forever too.
 *
 * Registered by the package's own provider; the engine knows no names.
 */
final class BottomLayers
{
    /** @var array<string, ThemeManifest> */
    private array $layers = [];

    public function add(string $name, string $path): ThemeManifest
    {
        return $this->layers[$name] = new ThemeManifest($name, rtrim(str_replace('\\', '/', $path), '/'), false);
    }

    public function get(string $name): ?ThemeManifest
    {
        return $this->layers[$name] ?? null;
    }

    /** @return list<ThemeManifest> */
    public function all(): array
    {
        return array_values($this->layers);
    }
}
