<?php

declare(strict_types=1);

namespace WebxUi\Themes\Console;

use Illuminate\Console\Command;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\Contracts\SyncStep;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

/**
 * Brings the site up to date with its layers (spec §17): their built files and assets (§13.2) —
 * the themes' and those of the packages below them, the widgets — and then whatever the packages
 * registered as a {@see SyncStep}: the block types of the layers, when `webx-ui/module-blocks` is
 * installed (§9.2). Run it after `composer update` — a theme update that was installed but not
 * synced still renders with the old stylesheet and the old blocks.
 *
 * Idempotent: `webx:setup`, the site's `post-update-cmd` and whoever updates many sites at once
 * all call it. A refused line — a block type that breaks §8, a slug the site already uses for a
 * type of its own — makes it exit non-zero after doing everything else.
 */
class SyncCommand extends Command
{
    protected $signature = 'webx:theme:sync
        {--force : Copy the files again even if they have not changed, and overwrite block types changed on this site}
        {--dry-run : Say what would be done without touching public/ or the database}';

    protected $description = 'Publish the built files of the packaged theme layers to public/themes, and put the block types of the layers on the site';

    public function handle(ThemeChain $chain, BottomLayers $bottom, ThemeAssets $assets): int
    {
        if ($chain->isEmpty()) {
            $this->components->info('No theme is configured (webx-themes.theme), nothing to sync.');

            return self::SUCCESS;
        }

        foreach ([...$chain->layers, ...$bottom->all()] as $layer) {
            if ($layer->local) {
                $this->components->twoColumnDetail($layer->name, 'local, built by the site\'s Vite');

                continue;
            }

            $this->components->twoColumnDetail($layer->name, $this->sync($layer, $assets));
        }

        $refused = 0;

        /** @var SyncStep $step */
        foreach ($this->laravel->tagged(SyncStep::TAG) as $step) {
            $lines = $step->sync($chain, (bool) $this->option('force'), (bool) $this->option('dry-run'));

            if ($lines === []) {
                continue;
            }

            $this->newLine();
            $this->line('  <options=bold>'.$step->title().'</>');

            foreach ($lines as $line) {
                if ($line->refused) {
                    $refused++;
                    $this->components->twoColumnDetail($line->subject, '<fg=red>'.$line->detail.'</>');
                } else {
                    $this->components->twoColumnDetail($line->subject, $line->detail);
                }
            }
        }

        if ($refused > 0) {
            $this->newLine();
            $this->components->error(sprintf('%d refused — the rest is synced.', $refused));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function sync(ThemeManifest $layer, ThemeAssets $assets): string
    {
        if ($this->option('dry-run')) {
            $hash = $assets->hash($layer);

            return match (true) {
                $hash === null => 'nothing to publish',
                $hash === $assets->current($layer) && ! $this->option('force') => 'unchanged',
                default => 'would publish '.$hash,
            };
        }

        return match ($assets->publish($layer, (bool) $this->option('force'))) {
            'published' => 'published '.$assets->current($layer),
            'unchanged' => 'unchanged',
            'nothing' => 'nothing to publish',
        };
    }
}
