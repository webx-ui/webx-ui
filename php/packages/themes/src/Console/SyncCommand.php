<?php

declare(strict_types=1);

namespace WebxUi\Themes\Console;

use Illuminate\Console\Command;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\Contracts\SyncStep;
use WebxUi\Themes\ModuleStyles;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeIcons;
use WebxUi\Themes\ThemeManifest;

/**
 * Brings the site up to date with its layers (spec §17): their built files and assets (§13.2) —
 * the themes' and those of the packages below them, the widgets; of a local theme only its
 * `assets/`, since the site's Vite builds the rest — the base look of the installed
 * modules as one stylesheet (§10.2), the icons of the chain as one sprite (§13.1), and then
 * whatever the packages
 * registered as a {@see SyncStep}: the block types of the layers, when `webx-ui/module-blocks` is
 * installed (§9.2). Run it after `composer update` — a theme update that was installed but not
 * synced still renders with the old stylesheet and the old blocks.
 *
 * Idempotent: `webx:setup`, the site's `post-update-cmd` and whoever updates many sites at once
 * all call it. A refused line — a block type that breaks §8, a slug the site already uses for a
 * type of its own — makes it exit non-zero after doing everything else. `--adopt` names what the
 * steps may take over from the site, which they never do unasked: for the block types, only a
 * copy of the blocks demo that nobody changed.
 */
class SyncCommand extends Command
{
    protected $signature = 'webx:theme:sync
        {--force : Copy the files again even if they have not changed, and overwrite block types changed on this site}
        {--dry-run : Say what would be done without touching public/ or the database}
        {--adopt=* : Hand these block types of the site to the theme — only an untouched copy of the blocks demo (comma-separated or repeated)}';

    protected $description = 'Publish the built files and assets of the theme layers to public/themes, and put the block types of the layers on the site';

    public function handle(ThemeChain $chain, BottomLayers $bottom, ThemeAssets $assets, ModuleStyles $modules, ThemeIcons $icons): int
    {
        // Without a theme only the packages below the themes: the widgets' scripts, which
        // `@webxWidgets` links on a site that has none. Nothing else of a chain is there to sync.
        if ($chain->isEmpty()) {
            if ($bottom->all() === []) {
                $this->components->info('No theme is configured (webx-themes.theme), nothing to sync.');

                return self::SUCCESS;
            }

            $this->components->info('No theme is configured (webx-themes.theme): only the packages below the themes.');

            foreach ($bottom->all() as $layer) {
                $this->components->twoColumnDetail($layer->name, $this->sync($layer, $assets));
            }

            return self::SUCCESS;
        }

        foreach ([...$chain->layers, ...$bottom->all()] as $layer) {
            if ($layer->local) {
                // Its CSS and JS are the site's Vite's; its assets/ are published like a package's (§13.2).
                $published = $this->sync($layer, $assets);
                $detail = $published === 'nothing to publish' ? 'local, built by the site\'s Vite' : "local, built by the site's Vite; assets/ {$published}";

                $this->components->twoColumnDetail($layer->name, $detail);

                continue;
            }

            $this->components->twoColumnDetail($layer->name, $this->sync($layer, $assets));
        }

        $this->components->twoColumnDetail('modules — their base look', $this->modules($modules));
        $this->components->twoColumnDetail('icons — the sprite of the chain', $this->icons($icons));

        $refused = 0;
        $adopt = $this->adopt();

        /** @var SyncStep $step */
        foreach ($this->laravel->tagged(SyncStep::TAG) as $step) {
            $lines = $step->sync($chain, (bool) $this->option('force'), (bool) $this->option('dry-run'), $adopt);

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

    /**
     * `--adopt=hero,text` and `--adopt=hero --adopt=text` alike.
     *
     * @return list<string>
     */
    private function adopt(): array
    {
        $named = [];

        foreach ((array) $this->option('adopt') as $value) {
            foreach (explode(',', (string) $value) as $slug) {
                if (trim($slug) !== '') {
                    $named[] = trim($slug);
                }
            }
        }

        return array_values(array_unique($named));
    }

    /** The `resources/theme` stylesheets of the installed modules, as one file (§10.2). */
    private function modules(ModuleStyles $modules): string
    {
        $count = count($modules->sources());
        $count .= $count === 1 ? ' stylesheet' : ' stylesheets';

        if ($this->option('dry-run')) {
            $hash = $modules->hash();

            return match (true) {
                $hash === null => 'no module carries one',
                $hash === $modules->current() && ! $this->option('force') => 'unchanged',
                default => "would publish {$hash} ({$count})",
            };
        }

        return match ($modules->publish((bool) $this->option('force'))) {
            'published' => "published {$modules->current()} ({$count})",
            'unchanged' => 'unchanged',
            'nothing' => 'no module carries one',
        };
    }

    /** The `icons/*.svg` of the layers, as one sprite (§13.1). */
    private function icons(ThemeIcons $icons): string
    {
        $count = count($icons->names());
        $count .= $count === 1 ? ' icon' : ' icons';

        if ($this->option('dry-run')) {
            $hash = $icons->hash();

            return match (true) {
                $hash === null => 'no layer has icons/',
                $hash === $icons->current() && ! $this->option('force') => 'unchanged',
                default => "would publish {$hash} ({$count})",
            };
        }

        return match ($icons->publish((bool) $this->option('force'))) {
            'published' => "published {$icons->current()} ({$count})",
            'unchanged' => 'unchanged',
            'nothing' => 'no layer has icons/',
        };
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
