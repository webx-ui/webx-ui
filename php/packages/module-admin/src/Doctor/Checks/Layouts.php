<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\Paths;
use WebxUi\Admin\Panel\PackageRegistry;

/**
 * The seam between a module's public views and the site's own document.
 *
 * A module that has public pages carries a `layout` key, and `webx:panel --sync` points it at
 * `<x-layout>`. Three things go wrong afterwards, and every one of them is silent: the key
 * names a component that has since been renamed, and the page 500s; the key is empty, and the
 * page prints a bare document that looks like the stylesheet failed to load; or the layout is
 * there and has no `@stack('head')`, and everything a block type or a partial pushes — every
 * metatag, every canonical — is dropped. The last is the worst of the three, because the page
 * looks finished and you find out from a search engine.
 */
final class Layouts implements Check
{
    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
        private readonly Filesystem $files,
        private readonly Views $views,
        private readonly PackageRegistry $registry,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $found = [];
        $checked = [];

        foreach ($this->registry->packages() as $package) {
            $module = $package->module;
            $settings = $module === null ? null : $this->config->get("webx-{$module}");

            if (! is_array($settings) || ! array_key_exists('layout', $settings)) {
                continue;
            }

            $layout = is_string($settings['layout']) ? $settings['layout'] : '';

            if ($layout === '') {
                $found[] = Diagnosis::warn(
                    'Layout',
                    "config/webx-{$module}.php names no layout, so {$package->name} prints its own bare document — run `php artisan webx:panel --sync`.",
                );

                continue;
            }

            // One component, however many modules stand in it: saying the same thing six times
            // is how a report stops being read.
            if (in_array($layout, $checked, true)) {
                continue;
            }

            $checked[] = $layout;
            $found = [...$found, ...$this->component($layout, $module)];
        }

        return $found;
    }

    /**
     * @return list<Diagnosis>
     */
    private function component(string $layout, string $module): array
    {
        $view = 'components.'.$layout;

        try {
            $path = $this->views->getFinder()->find($view);
        } catch (InvalidArgumentException) {
            return [Diagnosis::fail(
                'Layout',
                "config/webx-{$module}.php stands the public pages in <x-{$layout}>, and there is no {$view} — every page of that module answers 500.",
            )];
        }

        $contents = (string) $this->files->get($path);
        $relative = Paths::short($path, $this->app->basePath());

        if (preg_match('/@stack\(\s*[\'"]head[\'"]\s*\)/', $contents) !== 1) {
            return [Diagnosis::fail(
                'Layout',
                "{$relative} has no @stack('head') — metatags pushed by a block or a partial are dropped, and the page still looks finished. Add it inside <head>.",
            )];
        }

        if (! str_contains($contents, '$head')) {
            return [Diagnosis::warn(
                'Layout',
                "{$relative} has @stack('head') but no {{ \$head }} slot — a view that fills the slot instead of pushing loses its metatags.",
            )];
        }

        return [Diagnosis::ok('Layout', "<x-{$layout}> takes both what is pushed and what is passed.")];
    }
}
