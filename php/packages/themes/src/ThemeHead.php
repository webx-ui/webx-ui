<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Foundation\Vite;

/**
 * What `@webxTheme` prints in `<head>` (spec §7.3, §13.2):
 *
 *     <style>:root { --site-…: … }</style>     the merged tokens, inline
 *     <link rel="stylesheet" href="/themes/…">  each packaged layer's theme.css, bottom first
 *     Vite tags                                 each local layer's src/css/theme.css and src/js/theme.js
 *
 * The tokens are inline so that a new preset or an edit in the panel needs no build. The
 * stylesheets go bottom up so the higher layer wins the cascade. Without a theme it prints
 * nothing at all: a site that has not adopted themes keeps the head it had.
 *
 * Nothing here throws on a page. A packaged layer that was not synced and a local layer that was
 * not built are skipped — the page comes out with less style, not as a 500 — and in debug mode
 * an HTML comment says which command was forgotten.
 */
class ThemeHead
{
    /** A local layer's entry points, by convention (§4): the same names as the files in a package's dist/. */
    public const array LOCAL_ENTRIES = ['src/css/theme.css', 'src/js/theme.js'];

    public function __construct(
        private readonly Application $app,
        private readonly ThemeChain $chain,
        private readonly Tokens $tokens,
        private readonly ThemeAssets $assets,
    ) {}

    public function render(): string
    {
        if ($this->chain->isEmpty()) {
            return '';
        }

        $html = [];
        $css = $this->tokens->css();

        if ($css !== '') {
            $html[] = "<style data-webx-theme>\n{$css}\n</style>";
        }

        foreach (array_reverse($this->chain->layers) as $layer) {
            $html[] = $layer->local ? $this->local($layer) : $this->packaged($layer);
        }

        return implode("\n", array_filter($html));
    }

    private function packaged(ThemeManifest $layer): string
    {
        if (! is_file($layer->path.'/dist/theme.css')) {
            return '';
        }

        $url = $this->assets->url($layer, 'theme.css', $this->app->make(UrlGenerator::class));

        if ($url === null) {
            return $this->hint("{$layer->name} is not published: php artisan webx:theme:sync");
        }

        return '<link rel="stylesheet" href="'.e($url).'">';
    }

    /**
     * The site's Vite builds a local theme, so its tags come from Vite — but only for entries
     * Vite knows: asking it for an entry missing from the manifest throws, and a site whose
     * `npm run build` has not run yet should still open.
     */
    private function local(ThemeManifest $layer): string
    {
        $base = rtrim(str_replace('\\', '/', $this->app->basePath()), '/').'/';

        if (! str_starts_with($layer->path.'/', $base)) {
            return '';
        }

        $entries = [];

        foreach (self::LOCAL_ENTRIES as $entry) {
            if (is_file($layer->path.'/'.$entry)) {
                $entries[] = substr($layer->path, strlen($base)).'/'.$entry;
            }
        }

        if ($entries === []) {
            return '';
        }

        $vite = $this->app->make(Vite::class);

        if (! $vite->isRunningHot()) {
            $built = $this->built();
            $missing = array_diff($entries, $built);
            $entries = array_values(array_intersect($entries, $built));

            if ($entries === []) {
                return $this->hint('the local theme is not built: npm run build ('.implode(', ', $missing).')');
            }
        }

        return (string) $vite($entries);
    }

    /**
     * The entries in Vite's manifest.
     *
     * @return list<string>
     */
    private function built(): array
    {
        $manifest = $this->app->publicPath('build/manifest.json');

        if (! is_file($manifest)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($manifest), true);

        return is_array($data) ? array_map('strval', array_keys($data)) : [];
    }

    private function hint(string $message): string
    {
        return $this->app->make('config')->get('app.debug') ? '<!-- webx-themes: '.e($message).' -->' : '';
    }
}
