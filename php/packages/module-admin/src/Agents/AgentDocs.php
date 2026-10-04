<?php

declare(strict_types=1);

namespace WebxUi\Admin\Agents;

use Illuminate\Filesystem\Filesystem;
use WebxUi\Admin\Panel\PackageRegistry;

/**
 * Which installed `webx-ui/*` packages carry a guide for code agents.
 *
 * Read from Composer's own list, like the panel's packages are: install a module and it shows
 * up, remove it and it is gone. Whether a package is documented is whether the file is there
 * in the installed copy, so a site on an older release lists what that release had.
 */
final class AgentDocs
{
    public const FILE = 'AGENTS.md';

    public const VENDOR = 'webx-ui/';

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

            if (! is_string($name) || ! str_starts_with($name, self::VENDOR)) {
                continue;
            }

            $description = is_string($package['description'] ?? null) ? trim($package['description']) : '';

            $docs[$name] = new AgentDoc(
                name: $name,
                description: $description,
                documented: $this->files->exists($this->directory($path, $package).'/'.self::FILE),
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
}
