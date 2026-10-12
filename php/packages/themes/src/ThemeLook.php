<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebxUi\Themes\Blocks\LayerBlocks;

/**
 * What a chain gives a site, without saying which layer gives it: the merged tokens and the
 * preset, the winning file of every view, picture and icon, every block type, every font face,
 * every word of the layout. Two chains with the same look draw the same pages.
 *
 * `webx:theme:extract` and `webx:theme:eject` move a layer's files from one place to another
 * and promise the site does not change; this is how they keep the promise — the look before,
 * the look after, and every difference named (spec §14.3). The stylesheets are the one part it
 * cannot see: the local theme's CSS is built by the site's Vite, the package's by its own, and
 * both are the same sources, moved.
 */
final class ThemeLook
{
    /**
     * @return array{
     *     tokens: array<string, string>,
     *     preset: string|null,
     *     views: array<string, string>,
     *     assets: array<string, string>,
     *     icons: array<string, string>,
     *     blocks: array<string, string>,
     *     fonts: list<string>,
     *     words: array<string, array<mixed>>
     * }
     */
    public static function of(ThemeChain $chain): array
    {
        $tokens = new Tokens($chain);
        $icons = [];

        foreach (array_reverse($chain->layers) as $layer) {
            foreach (ThemeIcons::files($layer) as $name => $file) {
                $icons[$name] = self::hash($file);
            }
        }

        ksort($icons, SORT_STRING);

        return [
            'tokens' => $tokens->values(),
            'preset' => $tokens->preset(),
            'views' => self::winners($chain, 'views'),
            'assets' => self::winners($chain, 'assets'),
            'icons' => $icons,
            'blocks' => array_map(
                static fn ($block): string => (string) json_encode($block->document),
                LayerBlocks::of($chain)->set(),
            ),
            'fonts' => array_map(
                static fn (array $face): string => "{$face['family']} {$face['weight']} {$face['style']}: ".implode(', ', array_map(
                    static fn (string $script, string $file): string => $script.'='.self::hash($face['layer']->path.'/assets/'.$file),
                    array_keys($face['files']),
                    $face['files'],
                )),
                ThemeFonts::faces($chain),
            ),
            'words' => self::words($chain),
        ];
    }

    /**
     * Every difference between two looks, a line each; empty when they are the same.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    public static function differences(array $before, array $after): array
    {
        $lines = [];

        if ($before['preset'] !== $after['preset']) {
            $lines[] = sprintf('preset: %s before, %s after', $before['preset'] ?? 'none', $after['preset'] ?? 'none');
        }

        foreach (['tokens' => 'token', 'views' => 'view', 'assets' => 'asset', 'icons' => 'icon', 'blocks' => 'block type', 'words' => 'words of'] as $part => $what) {
            /** @var array<string, mixed> $old */
            $old = $before[$part];
            /** @var array<string, mixed> $new */
            $new = $after[$part];

            foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
                $lines[] = match (true) {
                    ! array_key_exists($key, $new) => "{$what} {$key}: gone",
                    ! array_key_exists($key, $old) => "{$what} {$key}: new",
                    $old[$key] !== $new[$key] => "{$what} {$key}: changed",
                    default => null,
                };
            }
        }

        foreach (array_diff($before['fonts'], $after['fonts']) as $face) {
            $lines[] = "font {$face}: gone or changed";
        }

        foreach (array_diff($after['fonts'], $before['fonts']) as $face) {
            $lines[] = "font {$face}: new";
        }

        return array_values(array_filter($lines));
    }

    /**
     * A sentence for a command: how much of the look stayed the same.
     *
     * @param  array{tokens: array<string, string>, preset: string|null, views: array<string, string>, assets: array<string, string>, icons: array<string, string>, blocks: array<string, string>, fonts: list<string>, words: array<string, array<mixed>>}  $look
     */
    public static function summary(array $look): string
    {
        return sprintf(
            '%d tokens%s, %d views, %d block types, %d icons, %d assets, %d font faces, the words of %d %s',
            count($look['tokens']),
            $look['preset'] === null ? '' : ' (preset '.$look['preset'].')',
            count($look['views']),
            count($look['blocks']),
            count($look['icons']),
            count($look['assets']),
            count($look['fonts']),
            count($look['words']),
            count($look['words']) === 1 ? 'language' : 'languages',
        );
    }

    /**
     * Path under the directory → hash of the file the highest layer that has it carries.
     *
     * @return array<string, string>
     */
    private static function winners(ThemeChain $chain, string $directory): array
    {
        $files = [];

        foreach (array_reverse($chain->layers) as $layer) {
            $root = $layer->path.'/'.$directory;

            if (! is_dir($root)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                $files[substr($path, strlen($root) + 1)] = self::hash($path);
            }
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * `lang/<locale>/<group>.php` of the layers, merged bottom up by key, per locale and group.
     *
     * @return array<string, array<mixed>>
     */
    private static function words(ThemeChain $chain): array
    {
        $words = [];

        foreach (array_reverse($chain->layers) as $layer) {
            foreach (glob($layer->path.'/lang/*/*.php') ?: [] as $file) {
                $file = str_replace('\\', '/', $file);
                $key = basename(dirname($file)).'/'.basename($file, '.php');
                $lines = require $file;

                $words[$key] = array_replace_recursive($words[$key] ?? [], is_array($lines) ? $lines : []);
            }
        }

        ksort($words, SORT_STRING);

        return $words;
    }

    private static function hash(string $file): string
    {
        return is_file($file) ? hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($file))) : '';
    }
}
