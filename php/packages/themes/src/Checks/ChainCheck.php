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
use WebxUi\Themes\ThemeChain;
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

    /**
     * @param  list<ThemeManifest>  $bottom  The packages below the themes ({@see BottomLayers}).
     * @param  list<string>  $modules  Directories of modules whose `resources/theme` is read; null asks Composer.
     */
    public function __construct(
        private readonly ThemeChain $chain,
        private readonly array $bottom = [],
        private readonly ?array $modules = null,
    ) {}

    /**
     * @return list<array{file: string, line: int|null, message: string}>
     */
    public function run(): array
    {
        $this->findings = [];
        $this->read = [];
        $this->blocks = 0;

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

        return $this->findings;
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
