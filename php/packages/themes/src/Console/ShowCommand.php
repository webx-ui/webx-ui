<?php

declare(strict_types=1);

namespace WebxUi\Themes\Console;

use Illuminate\Console\Command;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeReport;

/**
 * What the theme chain of this site gives and where each piece comes from (spec §16, §17): the
 * first thing an agent runs before it changes the look (rule 2), so that it edits the layer that
 * owns a value and does not write what a lower layer already has. `--json` for a script or an agent.
 */
class ShowCommand extends Command
{
    protected $signature = 'webx:theme:show {--json : Print the report as JSON}';

    protected $description = 'Show the theme chain: its layers, the tokens and where each value comes from, light and dark, the preset, the views and what they override, the block types, icons, fonts and module stylesheets';

    public function handle(ThemeChain $chain): int
    {
        if ($chain->isEmpty()) {
            if ($this->option('json')) {
                $this->line('null');
            } else {
                $this->components->info('No theme is configured (webx-themes.theme): the site\'s own resources/views is all there is.');
            }

            return self::SUCCESS;
        }

        try {
            $report = $this->laravel->make(ThemeReport::class)->toArray();
        } catch (ThemeException $failure) {
            $this->components->error($failure->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->print($report);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function print(array $report): void
    {
        $this->section('Layers, top first');

        foreach ($report['layers'] as $layer) {
            $this->line(sprintf('  %s  %s%s', $layer['name'], $layer['path'], $layer['local'] ? '  (local)' : ''));
        }

        foreach ($report['below'] as $name) {
            $this->line("  {$name}  (below the themes)");
        }

        $this->section('Preset');
        $presets = array_keys($report['presets']);
        $this->line('  '.($report['preset'] ?? 'none').($presets === [] ? '' : '  — the chain has: '.implode(', ', $presets)));
        $this->line('  Chosen by "preset" in the manifest of a layer; the panel does not change the look.');

        $this->section('Tokens');
        // The dark columns only for a chain with a dark scheme (§7.6): elsewhere they would be empty.
        $dark = array_filter(array_column($report['tokens'], 'dark')) !== [];

        $this->table(['Token', 'Type', 'Value', 'From', ...($dark ? ['Dark', 'Dark from'] : [])], array_map(
            static fn (string $name, array $token): array => [
                "--site-{$name}",
                $token['type'],
                $token['resolved'] === null ? $token['value'] : "{$token['value']} = {$token['resolved']}",
                $token['from'],
                ...($dark ? [$token['dark'] ?? 'as light', $token['dark_from'] ?? ''] : []),
            ],
            array_keys($report['tokens']),
            $report['tokens'],
        ));

        $this->section('Views');
        $this->table(['View', 'Served by', 'Hides'], array_map(
            static fn (array $view): array => [$view['view'], $view['from'], implode(', ', $view['hides'])],
            $report['views'],
        ));

        $this->section('Block types');
        $this->table(['Slug', 'From', 'Hides'], array_map(
            static fn (array $block): array => [$block['slug'], $block['from'], implode(', ', $block['hides'])],
            $report['blocks'],
        ));

        $this->section('Icons');
        $byLayer = [];

        foreach ($report['icons'] as $name => $layer) {
            $byLayer[$layer][] = $name;
        }

        foreach ($byLayer as $layer => $names) {
            $this->line("  {$layer}: ".implode(', ', $names));
        }

        if ($byLayer === []) {
            $this->line('  none');
        }

        $this->section('Fonts');

        foreach ($report['fonts'] as $face) {
            $this->line(sprintf('  %s %s %s — %s  (%s)', $face['family'], $face['weight'], $face['style'], implode(', ', $face['scripts']), $face['from']));
        }

        if ($report['fonts'] === []) {
            $this->line('  none of its own — the reader\'s fonts draw the page');
        }

        $this->section('Module stylesheets (below the themes)');
        $this->line('  '.($report['modules'] === [] ? 'none' : implode(', ', $report['modules'])));
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");
    }
}
