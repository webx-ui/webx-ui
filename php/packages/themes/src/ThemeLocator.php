<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Composer\InstalledVersions;
use WebxUi\Themes\Exceptions\ThemeException;

/**
 * Turns a reference — what `webx-themes.theme` or a `uses` entry holds — into a manifest.
 *
 * A reference is a Composer package name or a directory of the site. Names are tried first,
 * because `webx-ui/theme-default` is also a perfectly valid relative path that simply does not
 * exist; a directory is tried only when no package answers to the name.
 */
class ThemeLocator
{
    /** @var array<string, string> */
    private array $packages = [];

    public function __construct(private readonly string $basePath) {}

    /**
     * Where a packaged theme lives when Composer does not know it: the monorepo, a theme under
     * development next to the site, the fixtures of a test.
     */
    public function register(string $name, string $path): void
    {
        $this->packages[$name] = $path;
    }

    public function locate(string $reference, ?string $usedBy = null): ThemeManifest
    {
        $package = $this->packagePath($reference);

        if ($package !== null) {
            return ThemeManifest::fromPackage($package);
        }

        $directory = $this->isAbsolute($reference) ? $reference : $this->basePath.'/'.$reference;

        if (is_file($directory.'/theme.json')) {
            return ThemeManifest::fromLocal($directory, $reference);
        }

        throw ThemeException::notFound($reference, $usedBy);
    }

    private function packagePath(string $name): ?string
    {
        if (isset($this->packages[$name])) {
            return $this->packages[$name];
        }

        if (! str_contains($name, '/') || ! InstalledVersions::isInstalled($name)) {
            return null;
        }

        return InstalledVersions::getInstallPath($name);
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('~^[A-Za-z]:[/\\\\]~', $path) === 1;
    }
}
