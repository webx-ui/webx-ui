<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use WebxUi\Admin\Panel\PackageRegistry;
use WebxUi\Admin\Setup\Catalogue;

/**
 * The modules a site can have, and which of them it has — for a person, or for a script.
 *
 * The list is the one `webx:setup` offers, so a tool that builds sites reads it from here rather
 * than keeping a copy that goes stale a release later. Everything comes from the catalogue and
 * from `vendor/composer/installed.json`: no database, so it answers on a machine where the site
 * has none yet.
 *
 * A module that is installed but not in the catalogue — one of the site's own, or a third
 * party's — is listed as well, by what its package says under `extra.webx`.
 */
final class ModulesCommand extends Command
{
    protected $signature = 'webx:modules
                            {--json : Print the list as JSON, for a script to read}';

    protected $description = 'List the modules a site can have and say which are installed';

    public function handle(Catalogue $catalogue, PackageRegistry $registry): int
    {
        $modules = [...$catalogue->describe(), ...$this->outsiders($catalogue, $registry)];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                ['modules' => $modules],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return self::SUCCESS;
        }

        $this->table(
            ['Id', 'Package', 'Default', 'Requires', 'Installed'],
            array_map(static fn (array $module): array => [
                $module['id'],
                $module['package'],
                $module['default'] ? 'yes' : '',
                implode(', ', $module['requires']),
                $module['installed'] ? 'yes' : '',
            ], $modules),
        );

        $this->components->twoColumnDetail('Add one', 'php artisan webx:module:add <vendor/package>');

        return self::SUCCESS;
    }

    /**
     * Installed panel modules the catalogue does not know.
     *
     * @return list<array{id: string, package: string, npm: string|null, label: string, default: bool, requires: list<string>, installed: bool}>
     */
    private function outsiders(Catalogue $catalogue, PackageRegistry $registry): array
    {
        $known = $catalogue->ids();
        $found = [];

        foreach ($registry->packages() as $package) {
            if ($package->module === null || ! $package->wiresThePanel() || $catalogue->idFor($package->name) !== null) {
                continue;
            }

            // An id the catalogue already uses would read as that module; the package name is
            // what tells them apart.
            $id = in_array($package->module, $known, true) ? $package->name : $package->module;

            $found[] = [
                'id' => $id,
                'package' => $package->name,
                'npm' => array_key_first($package->npm),
                'label' => $package->name,
                'default' => false,
                'requires' => [],
                'installed' => true,
            ];
        }

        return $found;
    }
}
