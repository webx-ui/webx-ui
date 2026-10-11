<?php

declare(strict_types=1);

namespace WebxUi\Themes\Checks;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebxUi\Themes\Blocks\LayerBlocks;
use WebxUi\Themes\BottomLayers;
use WebxUi\Themes\Exceptions\ThemeException;
use WebxUi\Themes\ModuleStyles;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeIcons;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Themes\Vocabulary;

/**
 * What `webx:theme:check` reads (spec §17, §8): the tokens.json of every layer — the one place a
 * literal value is legal, so there it has to be a valid one — and every stylesheet of the chain
 * against {@see CssCheck}: `src/css` of the theme layers, `resources/css` and `resources/theme` of
 * the packages below them (the widgets), `resources/theme` of the installed modules (§10.2).
 *
 * And the block types of the layers (§9.2): each `blocks/*.json` is a block document, its
 * styles know only tokens — a type that does not is refused by sync, so it is said here first —
 * and a type that overrides one of a lower layer keeps every field of it, with the same type.
 *
 * And the icons (§13.1): each `icons/*.svg` of a layer can become a symbol that follows the
 * colour of the text, and the views and block templates of the layers name only icons the chain
 * has, never an inline `<svg>` ({@see IconCheck}); the pictures they ask for with `theme_asset()`
 * are files under `assets/` of a layer (§13.2, {@see AssetCheck}). And every language a layer
 * declares in `locales` has the words of the layout that `en` has (§15.2, {@see LineCheck}). And
 * the fonts of the layers are files of their own that cover the scripts of the site's languages
 * and of the languages the layer declares (§15.3, {@see FontCheck}).
 *
 * Built files are not read: `dist/` is what the sources became, plus the stylesheets of the
 * libraries a widget builds in (Swiper, PhotoSwipe, Leaflet), which are not ours to rewrite.
 */
final class ChainCheck
{
    /** @var list<array{file: string, line: int|null, message: string}> */
    private array $findings = [];

    /** @var list<string> */
    private array $read = [];

    private int $blocks = 0;

    private int $icons = 0;

    private int $assets = 0;

    private int $locales = 0;

    /** @var list<array{family: string, weight: string, style: string, files: array<string, string>, layer: ThemeManifest}> */
    private array $faces = [];

    /**
     * @param  list<ThemeManifest>  $bottom  The packages below the themes ({@see BottomLayers}).
     * @param  list<string>  $modules  Directories of modules whose `resources/theme` is read; null asks Composer.
     * @param  list<string>  $siteLocales  The languages the site publishes in, which its fonts have to draw.
     */
    public function __construct(
        private readonly ThemeChain $chain,
        private readonly array $bottom = [],
        private readonly ?array $modules = null,
        private readonly array $siteLocales = [],
    ) {}

    /**
     * @return list<array{file: string, line: int|null, message: string}>
     */
    public function run(): array
    {
        $this->findings = [];
        $this->read = [];
        $this->blocks = 0;
        $this->icons = 0;
        $this->assets = 0;
        $this->locales = 0;
        $this->faces = [];

        try {
            $vocabulary = Vocabulary::forChain($this->chain);
        } catch (ThemeException $failure) {
            $this->findings[] = ['file' => '', 'line' => null, 'message' => $failure->getMessage()];
            $vocabulary = Vocabulary::base();
        }

        foreach (array_reverse($this->chain->layers) as $layer) {
            $this->tokens($layer, $vocabulary);
        }

        foreach ($this->stylesheets() as $file) {
            $this->read[] = $file;

            foreach (CssCheck::check((string) file_get_contents($file), $vocabulary) as $finding) {
                $this->findings[] = ['file' => $file, 'line' => $finding->line, 'message' => $finding->message()];
            }
        }

        $this->layerBlocks($vocabulary);
        $this->checkIcons();

        // And the words of the layout (§15.2, rule 26): each layer, in each language it declares.
        foreach ($this->chain->layers as $layer) {
            $lines = LineCheck::check($layer);
            $this->locales += $lines['locales'];
            array_push($this->findings, ...$lines['findings']);
        }

        // And the fonts (§15.3, rule 27): files of the layers, one per script the languages need.
        $fonts = FontCheck::check($this->chain, $this->siteLocales);
        $this->faces = $fonts['faces'];
        array_push($this->findings, ...$fonts['findings']);

        return $this->findings;
    }

    /** How many languages the layers declare for their words, summed over the layers that have words. */
    public function locales(): int
    {
        return $this->locales;
    }

    /**
     * The faces of the chain the last run read, after a higher layer replaced a family.
     *
     * @return list<array{family: string, weight: string, style: string, files: array<string, string>, layer: ThemeManifest}>
     */
    public function faces(): array
    {
        return $this->faces;
    }

    /** How many icons the chain has, after the higher layer won by name. */
    public function icons(): int
    {
        return $this->icons;
    }

    /**
     * The icons of the layers and the templates that name them (§13.1, rule 24): each file can
     * become a symbol that follows the colour of the text, and each `<x-theme::icon>` in a view
     * or a block template of a layer names one the chain has.
     */
    private function checkIcons(): void
    {
        $names = [];

        foreach ($this->chain->layers as $layer) {
            foreach (ThemeIcons::files($layer) as $name => $file) {
                foreach (ThemeIcons::problems($name, (string) file_get_contents($file)) as $problem) {
                    $this->findings[] = ['file' => $file, 'line' => null, 'message' => $problem];
                }

                if (preg_match(ThemeIcons::NAME_PATTERN, $name) === 1) {
                    $names[$name] = true;
                }
            }
        }

        $names = array_keys($names);
        $this->icons = count($names);

        // And the pictures they ask for (§13.2): a file under assets/ of some layer.
        $exists = fn (string $path): bool => ThemeAssets::source($this->chain, $path) !== null;

        foreach ($this->templates() as ['file' => $path, 'line' => $offset, 'source' => $template]) {
            $this->assets += count(AssetCheck::calls($template));

            foreach ([...IconCheck::check($template, $names), ...AssetCheck::check($template, $exists)] as $finding) {
                $this->findings[] = $offset === null
                    ? ['file' => $path, 'line' => $finding['line'], 'message' => $finding['message']]
                    : ['file' => $path, 'line' => $offset, 'message' => "template, line {$finding['line']}: {$finding['message']}"];
            }
        }
    }

    /**
     * The views of the layers and the templates of their block types; `line` is where a block's
     * template starts in its document, null for a view.
     *
     * @return list<array{file: string, line: int|null, source: string}>
     */
    private function templates(): array
    {
        $templates = [];

        foreach ($this->chain->viewPaths() as $directory) {
            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if (str_ends_with($file->getFilename(), '.blade.php')) {
                    $path = str_replace('\\', '/', $file->getPathname());
                    $templates[] = ['file' => $path, 'line' => null, 'source' => (string) file_get_contents($path)];
                }
            }
        }

        foreach (LayerBlocks::of($this->chain)->all() as $block) {
            $templates[] = ['file' => $block->file, 'line' => $block->templateLine(), 'source' => $block->template()];
        }

        return $templates;
    }

    /** How many `theme_asset()` calls with a literal path the templates of the chain make. */
    public function assets(): int
    {
        return $this->assets;
    }

    /** How many block documents of the layers the last run read. */
    public function blocks(): int
    {
        return $this->blocks;
    }

    /**
     * The stylesheets the last run read.
     *
     * @return list<string>
     */
    public function read(): array
    {
        return $this->read;
    }

    private function layerBlocks(Vocabulary $vocabulary): void
    {
        $blocks = LayerBlocks::of($this->chain);

        foreach ($blocks->problems() as $problem) {
            $this->findings[] = $problem;
        }

        foreach ($blocks->all() as $block) {
            $this->blocks++;

            foreach (CssCheck::check($block->styles(), $vocabulary) as $finding) {
                $this->findings[] = ['file' => $block->file, 'line' => $block->stylesLine(), 'message' => "styles, line {$finding->line}: {$finding->message()}"];
            }
        }

        foreach ($blocks->incompatible() as $finding) {
            $this->findings[] = $finding;
        }
    }

    /** Every value of the layer's defaults and presets: a name the vocabulary has, a value of its type. */
    private function tokens(ThemeManifest $layer, Vocabulary $vocabulary): void
    {
        try {
            $file = $layer->tokens();
        } catch (ThemeException $failure) {
            $this->findings[] = ['file' => $layer->path.'/tokens.json', 'line' => null, 'message' => $failure->getMessage()];

            return;
        }

        if (! is_file($file->path)) {
            return;
        }

        $source = (string) file_get_contents($file->path);
        $sets = ['defaults' => $file->defaults];

        foreach ($file->presets as $name => $preset) {
            $sets["preset \"{$name}\""] = $preset['tokens'];
        }

        foreach ($sets as $where => $values) {
            foreach ($values as $name => $value) {
                $type = $vocabulary->type($name);
                $message = match (true) {
                    $type === null => "{$where} sets \"{$name}\", which is not in the vocabulary — a theme adds a name under \"vocabulary\" first",
                    ! $type->accepts($value) => "{$where} sets \"{$name}\" to \"{$value}\", which is not a valid {$type->value}",
                    default => null,
                };

                if ($message !== null) {
                    $at = strpos($source, '"'.$name.'"');
                    $this->findings[] = ['file' => $file->path, 'line' => $at === false ? null : substr_count($source, "\n", 0, $at) + 1, 'message' => $message];
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function stylesheets(): array
    {
        $directories = [];

        foreach ($this->chain->layers as $layer) {
            $directories[] = $layer->path.'/src/css';
        }

        foreach ($this->bottom as $layer) {
            $directories[] = $layer->path.'/resources/css';
            $directories[] = $layer->path.'/resources/theme';
        }

        foreach ($this->modules ?? ModuleStyles::installed() as $module) {
            $directories[] = rtrim(str_replace('\\', '/', $module), '/').'/resources/theme';
        }

        $files = [];

        foreach (array_unique($directories) as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if (strtolower($file->getExtension()) === 'css') {
                    $files[] = str_replace('\\', '/', $file->getPathname());
                }
            }
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        return $files;
    }
}
