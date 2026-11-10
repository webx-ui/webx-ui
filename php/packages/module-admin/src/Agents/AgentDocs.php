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
                directory: $local ? $this->fromRoot($package) : null,
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
     * The package's directory from the site root, read from `install-path` without touching the
     * disk: `vendor/composer/../../modules/offices` is `modules/offices`.
     *
     * @param  array<array-key, mixed>  $package
     */
    private function fromRoot(array $package): ?string
    {
        $relative = is_string($package['install-path'] ?? null) ? $package['install-path'] : null;

        if ($relative === null) {
            return null;
        }

        $parts = [];

        foreach (explode('/', 'vendor/composer/'.str_replace('\\', '/', $relative)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part !== '..') {
                $parts[] = $part;
            } elseif (array_pop($parts) === null) {
                // Above the site root: not a folder of this site.
                return null;
            }
        }

        return $parts === [] ? null : implode('/', $parts);
    }
}
