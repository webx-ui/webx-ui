<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Panel\PackageRegistry;

/**
 * The regions of the layout the site declared, against the layout that is meant to print them
 * (§10 of the regions spec).
 *
 * A region exists where `<x-webx-blocks::region name="…">` stands in the layout, and nowhere else.
 * Declared and never printed, it is a section of the panel that edits nothing: the editor fills
 * the footer, publishes it, and the site goes on printing the footer from code — with no error
 * anywhere, because nothing is broken, only unconnected. Read as text from the layout's source,
 * like the `@stack('head')` of `Layouts`: the tag is a literal, and a layout that builds the name
 * at runtime is not something to guess about.
 */
final class Regions implements Check
{
    public function __construct(
        private readonly Repository $config,
        private readonly Filesystem $files,
        private readonly Views $views,
        private readonly PackageRegistry $registry,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $declared = $this->config->get('webx-blocks.regions', []);
        $names = array_values(array_filter(array_keys(is_array($declared) ? $declared : []), 'is_string'));

        if ($names === []) {
            return [];
        }

        $source = '';

        foreach ($this->layouts() as $layout) {
            try {
                $source .= "\n".$this->files->get($this->views->getFinder()->find('components.'.$layout));
            } catch (InvalidArgumentException) {
                // A missing layout is `Layouts`' to report, and it does, as a failure.
            }
        }

        if ($source === '') {
            return [];
        }

        // `:name="$x"`: the layout decides at runtime, and whatever it decides is not ours to judge.
        if (preg_match('/<x-webx-blocks::region\b[^>]*\s:name\s*=/', $source) === 1) {
            return [Diagnosis::ok('Regions', 'The layout names its regions at runtime; not checked.')];
        }

        $found = [];

        foreach ($names as $name) {
            $printed = preg_match('/<x-webx-blocks::region\b[^>]*\sname\s*=\s*(["\'])'.preg_quote($name, '/').'\1/', $source) === 1;

            $found[] = $printed
                ? Diagnosis::ok('Regions', "The layout prints the region [{$name}].")
                : Diagnosis::warn('Regions', "The region [{$name}] is declared in webx-blocks.regions, but the layout does not print it — what the panel publishes there never reaches the site. Add <x-webx-blocks::region name=\"{$name}\" fallback=\"…\" /> to the layout.");
        }

        return $found;
    }

    /**
     * Every layout a module stands its pages in, and the one the block editor's stage uses — the
     * first place a region would be printed. `layout` when nobody named one: the skeleton's.
     *
     * @return list<string>
     */
    private function layouts(): array
    {
        $layouts = [];

        foreach ($this->registry->packages() as $package) {
            $layout = $package->module === null ? null : $this->config->get("webx-{$package->module}.layout");

            if (is_string($layout) && $layout !== '') {
                $layouts[] = $layout;
            }
        }

        $stage = $this->config->get('webx-blocks.layout');

        if (is_string($stage) && $stage !== '') {
            $layouts[] = $stage;
        }

        return $layouts === [] ? ['layout'] : array_values(array_unique($layouts));
    }
}
