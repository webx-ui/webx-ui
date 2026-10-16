<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Doctor;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Snippets\Checks\PlaceFinder;
use WebxUi\Snippets\Models\Snippet;
use WebxUi\Snippets\Places;
use WebxUi\Themes\ThemeChain;
use WebxUi\Widgets\Widgets;

/**
 * The places in the command a deploy already runs (§10.2): a place declared and printed by no
 * view, codes left in a place the config no longer declares, codes waiting for consent on a site
 * that will never ask for it, and — outside production — why most codes are not on the page.
 *
 * Warnings, not failures: the site works, it just does not print what somebody saved.
 */
final class SnippetPlaces implements Check
{
    private const string SUBJECT = 'Snippets';

    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
        private readonly Places $places,
        private readonly PlaceFinder $finder,
        private readonly ThemeChain $chain,
        private readonly Widgets $widgets,
    ) {}

    public function run(): array
    {
        $found = [];
        $printed = $this->finder->find($this->viewPaths())['places'];

        foreach ($this->places->keys() as $place) {
            if (! isset($printed[$place])) {
                $found[] = Diagnosis::warn(self::SUBJECT, "place \"{$place}\" is declared but no view prints it — add <x-webx-snippets place=\"{$place}\" /> to the layout; php artisan webx:theme:check says which file.");
            }
        }

        $orphans = Snippet::query()
            ->whereNotIn('place', $this->places->keys())
            ->selectRaw('place, count(*) as total')
            ->groupBy('place')
            ->pluck('total', 'place');

        foreach ($orphans as $place => $total) {
            $found[] = Diagnosis::content(self::SUBJECT, sprintf(
                '%d %s in place "%s", which config/webx-snippets.php no longer declares — not printed. Move them in the panel, or declare the place again.',
                $total,
                (int) $total === 1 ? 'snippet is' : 'snippets are',
                $place,
            ));
        }

        $waiting = Snippet::query()->where('enabled', true)->where('consent', '!=', 'necessary')->count();

        if ($waiting > 0 && ($this->chain->isEmpty() || ! $this->widgets->consentReady())) {
            $found[] = Diagnosis::warn(self::SUBJECT, sprintf(
                '%d enabled %s for consent, and this site has no consent script to release %s — they are never printed. Configure a theme (webx-themes.theme) and run php artisan webx:theme:sync.',
                $waiting,
                $waiting === 1 ? 'snippet waits' : 'snippets wait',
                $waiting === 1 ? 'it' : 'them',
            ));
        }

        $environments = array_values(array_filter((array) $this->config->get('webx-snippets.environments', ['production']), is_string(...)));

        if (! $this->app->environment($environments)) {
            $hidden = Snippet::query()->where('enabled', true)->where('on_stage', false)->count();

            if ($hidden > 0) {
                $found[] = Diagnosis::ok(self::SUBJECT, sprintf(
                    'the environment is "%s": %d enabled %s without "on dev and stage too" %s not printed here, only in %s.',
                    $this->app->environment(),
                    $hidden,
                    $hidden === 1 ? 'snippet' : 'snippets',
                    $hidden === 1 ? 'is' : 'are',
                    implode(', ', $environments),
                ));
            }
        }

        if ($found === []) {
            return [Diagnosis::ok(self::SUBJECT, 'every place is printed by a view: '.implode(', ', $this->places->keys()).'.')];
        }

        return $found;
    }

    /**
     * The site's views (`view.paths`), then the theme's layers: the order Blade reads them in.
     *
     * @return list<string>
     */
    private function viewPaths(): array
    {
        $site = array_filter((array) $this->config->get('view.paths', []), static fn (mixed $path): bool => is_string($path) && is_dir($path));

        return [...array_values(array_map(static fn (string $path): string => str_replace('\\', '/', $path), $site)), ...$this->chain->viewPaths()];
    }
}
