<?php

declare(strict_types=1);

namespace WebxUi\Admin\Agents;

use Illuminate\Filesystem\Filesystem;
use WebxUi\Admin\Panel\PackageRegistry;
use WebxUi\Admin\Panel\Vendors;

/**
 * Which installed packages of ours — `webx-ui/*` and the site's own `site/*` ({@see Vendors}) —
 * carry a guide for code agents.
 *
 * Read from Composer's own list, like the panel's packages are: install a module and it shows
 * up, remove it and it is gone. Whether a package is documented is whether the file is there
 * in the installed copy, so a site on an older release lists what that release had.
 */
final class AgentDocs
{
    public const FILE = 'AGENTS.md';

    public function __construct(
        private readonly Filesystem $files,
        private ?string $installed = null,
    ) {}

    /** @return list<AgentDoc> */
    public function packages(): array
    {
        $path = $this->installed ??= PackageRegistry::locate();

        if ($path === null || ! $this->files->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) $this->files->get($path), true);
        $installed = is_array($decoded) ? ($decoded['packages'] ?? $decoded) : [];
        $docs = [];

        foreach (is_array($installed) ? $installed : [] as $package) {
            $name = is_array($package) ? ($package['name'] ?? null) : null;

            if (! is_string($name) || ! Vendors::ours($name)) {
                continue;
            }

            $description = is_string($package['description'] ?? null) ? trim($package['description']) : '';

            $local = Vendors::local($name);
            $docs[$name] = new AgentDoc(
                name: $name,
                description: $description,
                documented: $this->files->exists($this->directory($path, $package).'/'.self::FILE),
                local: $local,
                // A site module is linked where it is written, not through its symlink in `vendor`:
                // that is the file an agent edits.
                directory: $local ? $this->fromRoot($path, $package) : null,
            );
        }

        // Composer's order is the resolver's, and a file that reshuffles between two identical
        // runs is a diff nobody can review.
        ksort($docs);

        return array_values($docs);
    }

    /**
     * Where the package is installed: `install-path` is relative to `vendor/composer`.
     *
     * @param  array<array-key, mixed>  $package
     */
    private function directory(string $installed, array $package): string
    {
        $relative = is_string($package['install-path'] ?? null)
            ? $package['install-path']
            : '../'.$package['name'];

        return dirname($installed).'/'.$relative;
    }

    /**
     * The package's directory from the site root. Composer names the folder in `vendor` it
     * installed into (`../site/module-offices`), and a path repository with `symlink: true` makes
     * that folder a link to `modules/offices`: the link is followed, and a target inside the site
     * root is the answer. An `install-path` outside `vendor` (`../../modules/offices`) is read as
     * written, without the disk.
     *
     * @param  array<array-key, mixed>  $package
     */
    private function fromRoot(string $installed, array $package): ?string
    {
        $relative = is_string($package['install-path'] ?? null) ? $package['install-path'] : null;

        if ($relative === null) {
            return null;
        }

        $lexical = self::normalise('vendor/composer/'.str_replace('\\', '/', $relative));

        if ($lexical === null || ! str_starts_with($lexical.'/', 'vendor/')) {
            return $lexical;
        }

        $root = realpath(dirname($installed, 3));
        $target = realpath($this->directory($installed, $package));

        if ($root === false || $target === false) {
            return null;
        }

        $root = str_replace('\\', '/', $root).'/';
        $target = str_replace('\\', '/', $target);

        // A mirrored copy is still in vendor, and a link outside the site is not this site's folder.
        if (! str_starts_with($target, $root) || str_starts_with($target, $root.'vendor/')) {
            return null;
        }

        return substr($target, strlen($root));
    }

    /**
     * `vendor/composer/../../modules/offices` is `modules/offices`; null above the site root.
     */
    private static function normalise(string $path): ?string
    {
        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part !== '..') {
                $parts[] = $part;
            } elseif (array_pop($parts) === null) {
                return null;
            }
        }

        return $parts === [] ? null : implode('/', $parts);
    }
}
