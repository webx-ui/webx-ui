<?php

declare(strict_types=1);

namespace WebxUi\Themes\Console;

use Illuminate\Console\Command;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeChain;

/**
 * Brings the site up to date with its packaged layers (spec §17). For now that is their built
 * files and assets (§13.2); block types join in TH2. Run it after `composer update` — a theme
 * update that was installed but not synced still renders with the old stylesheet.
 */
class SyncCommand extends Command
{
    protected $signature = 'webx:theme:sync
        {--force : Copy the files again even if they have not changed}
        {--dry-run : Say what would be published without touching public/}';

    protected $description = 'Publish the built files of the packaged theme layers to public/themes';

    public function handle(ThemeChain $chain, ThemeAssets $assets): int
    {
        if ($chain->isEmpty()) {
            $this->components->info('No theme is configured (webx-themes.theme), nothing to sync.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($chain->layers as $layer) {
            if ($layer->local) {
                $this->components->twoColumnDetail($layer->name, 'local, built by the site\'s Vite');

                continue;
            }

            if ($dryRun) {
                $hash = $assets->hash($layer);
                $status = match (true) {
                    $hash === null => 'nothing to publish',
                    $hash === $assets->current($layer) && ! $this->option('force') => 'unchanged',
                    default => 'would publish '.$hash,
                };
            } else {
                $status = match ($assets->publish($layer, (bool) $this->option('force'))) {
                    'published' => 'published '.$assets->current($layer),
                    'unchanged' => 'unchanged',
                    'nothing' => 'nothing to publish',
                };
            }

            $this->components->twoColumnDetail($layer->name, $status);
        }

        return self::SUCCESS;
    }
}
