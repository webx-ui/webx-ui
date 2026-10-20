<?php

declare(strict_types=1);

namespace WebxUi\Admin\Support;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * The stylesheets of the panel this site built, for a page the panel serves without its Vue app.
 *
 * The inlined tokens make such a page look like the panel, but not quite like this site's: the
 * site's build is where its typeface is (the panel stub imports Inter), and a consent screen in
 * the platform's own font sat its button labels lower than the panel did, on the same tokens.
 * So the page links what the shell links — the stylesheets among \`webx-admin.assets\`, or the CSS
 * that Laravel's Vite manifest lists for the \`webx-admin.vite\` entry points — and the browser has
 * them cached from the panel already. The scripts stay out: they would start the panel.
 *
 * Nothing in development, when the Vite dev server injects CSS from JavaScript (\`public/hot\`),
 * and nothing before the first build: the inlined tokens stand alone then.
 */
final readonly class PanelStyles
{
    public function __construct(private Config $config) {}

    /**
     * @return list<string>
     */
    public function urls(): array
    {
        $urls = [];

        foreach ((array) $this->config->get('webx-admin.assets', []) as $asset) {
            if (is_string($asset) && str_contains(strtok($asset, '?') ?: $asset, '.css')) {
                $urls[] = $asset;
            }
        }

        $entries = array_values(array_filter((array) $this->config->get('webx-admin.vite', []), 'is_string'));

        if ($entries !== [] && ! is_file(public_path('hot'))) {
            foreach ($this->fromManifest($entries) as $file) {
                $urls[] = '/build/'.$file;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * The CSS of the entry points and of every chunk they import, as Vite would link it.
     *
     * @param  list<string>  $entries
     * @return list<string>
     */
    private function fromManifest(array $entries): array
    {
        $path = public_path('build/manifest.json');
        $json = is_file($path) ? file_get_contents($path) : false;
        $manifest = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($manifest)) {
            return [];
        }

        $css = [];
        $seen = [];
        $queue = $entries;

        while ($queue !== []) {
            $key = array_shift($queue);

            if (isset($seen[$key]) || ! is_array($manifest[$key] ?? null)) {
                continue;
            }

            $seen[$key] = true;
            $chunk = $manifest[$key];

            foreach ((array) ($chunk['css'] ?? []) as $file) {
                if (is_string($file)) {
                    $css[] = $file;
                }
            }

            foreach ((array) ($chunk['imports'] ?? []) as $import) {
                if (is_string($import)) {
                    $queue[] = $import;
                }
            }
        }

        return $css;
    }
}
