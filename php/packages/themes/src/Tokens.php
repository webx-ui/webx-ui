<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use WebxUi\Themes\Exceptions\ThemeException;

/**
 * The values the site's tokens end up with (spec §7.3), merged bottom up:
 *
 *     the bottom layer's defaults → … → the top layer's defaults → the preset the theme names
 *
 * A preset is merged down the chain the same way: a local theme may tune the base theme's
 * `night` without repeating it. Everything here is the theme's files, kept in git (§2, decision
 * 12: the panel does not edit the look), so a wrong name or a value of the wrong type in a
 * tokens.json, or a preset the chain does not have, is an exception, not a value quietly dropped.
 */
class Tokens
{
    private ?Vocabulary $vocabulary = null;

    /** @var array<string, TokenFile>|null */
    private ?array $files = null;

    /** @var array<string, string>|null */
    private ?array $values = null;

    /** @var array<string, string>|null */
    private ?array $sources = null;

    public function __construct(private readonly ThemeChain $chain) {}

    public function vocabulary(): Vocabulary
    {
        return $this->vocabulary ??= Vocabulary::forChain($this->chain);
    }

    /**
     * Every token the chain gives a value to, in the vocabulary's order.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $values = [];
        $sources = [];

        foreach ($this->files() as $layer => $file) {
            $values = [...$values, ...$file->defaults];
            $sources = [...$sources, ...array_fill_keys(array_keys($file->defaults), $layer)];
        }

        $preset = $this->preset();

        if ($preset !== null) {
            // Down the chain again, so each token says which layer's preset gave it.
            foreach ($this->files() as $layer => $file) {
                $tokens = $file->presets[$preset]['tokens'] ?? [];
                $values = [...$values, ...$tokens];
                $sources = [...$sources, ...array_fill_keys(array_keys($tokens), "{$layer}, preset {$preset}")];
            }
        }

        $ordered = [];
        $this->sources = [];

        foreach ($this->vocabulary()->names() as $name) {
            if (isset($values[$name])) {
                $ordered[$name] = $values[$name];
                $this->sources[$name] = $sources[$name];
            }
        }

        return $this->values = $ordered;
    }

    /**
     * Where each value of {@see values()} comes from: the layer whose tokens.json sets it, or
     * that layer and the preset — what `webx:theme:show` tells an agent before it edits a token.
     *
     * @return array<string, string>
     */
    public function sources(): array
    {
        $this->values();

        return $this->sources ?? [];
    }

    public function get(string $name): ?string
    {
        return $this->values()[$name] ?? null;
    }

    /**
     * The value with every `var(--site-…)` in it replaced by that token's value, for where no CSS
     * variable reaches — a letter (§7.5): `font-heading` of theme-default is
     * `var(--site-font-body)`, and a letter that printed it would have no font at all. A
     * reference to a token without a value, or one that comes back to itself, gives null.
     */
    public function resolved(string $name): ?string
    {
        return $this->resolve($name, []);
    }

    /** @param array<string, true> $seen */
    private function resolve(string $name, array $seen): ?string
    {
        $value = $this->get($name);

        if ($value === null || isset($seen[$name])) {
            return null;
        }

        $seen[$name] = true;
        $broken = false;

        $resolved = (string) preg_replace_callback('/var\(\s*--site-([A-Za-z0-9_-]+)\s*\)/', function (array $reference) use ($seen, &$broken): string {
            $value = $this->resolve($reference[1], $seen);
            $broken = $broken || $value === null;

            return $value ?? '';
        }, $value);

        return $broken ? null : $resolved;
    }

    /**
     * The preset in force: the one the highest layer names in its manifest (`"preset"`), or none.
     * A name no tokens.json of the chain declares is a mistake in the theme's files and is said.
     */
    public function preset(): ?string
    {
        foreach ($this->chain->layers as $layer) {
            if ($layer->preset === null) {
                continue;
            }

            if (! isset($this->presets()[$layer->preset])) {
                $known = array_keys($this->presets());

                throw ThemeException::invalidManifest(
                    $layer->path.($layer->local ? '/theme.json' : '/composer.json'),
                    "it names the preset \"{$layer->preset}\", which no tokens.json of the chain declares"
                        .($known === [] ? '.' : ' (it has: '.implode(', ', $known).').'),
                );
            }

            return $layer->preset;
        }

        return null;
    }

    /**
     * Every preset of the chain, merged down it: the title of the highest layer that names one,
     * the tokens of all of them.
     *
     * @return array<string, array{title: string, tokens: array<string, string>}>
     */
    public function presets(): array
    {
        $presets = [];

        foreach ($this->files() as $file) {
            foreach ($file->presets as $name => $preset) {
                $presets[$name] = [
                    'title' => $preset['title'],
                    'tokens' => [...($presets[$name]['tokens'] ?? []), ...$preset['tokens']],
                ];
            }
        }

        return $presets;
    }

    /**
     * `:root { --site-…: … }` for the inline `<style>` of `@webxTheme`. Every value is checked
     * against its type once more on the way out: the values are already checked on the way in,
     * and this is the line that must hold if one of those checks is ever loosened.
     */
    public function css(): string
    {
        $lines = [];

        foreach ($this->values() as $name => $value) {
            if ($this->vocabulary()->accepts($name, $value)) {
                $lines[] = "  --site-{$name}: {$value};";
            }
        }

        return $lines === [] ? '' : ":root {\n".implode("\n", $lines)."\n}";
    }

    /**
     * The tokens.json of every layer, bottom first, checked against the vocabulary.
     *
     * @return array<string, TokenFile>
     */
    private function files(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }

        $files = [];

        foreach (array_reverse($this->chain->layers) as $layer) {
            $file = $layer->tokens();

            $this->check($file->path, $file->defaults, 'defaults');

            foreach ($file->presets as $name => $preset) {
                $this->check($file->path, $preset['tokens'], "preset \"{$name}\"");
            }

            $files[$layer->name] = $file;
        }

        return $this->files = $files;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function check(string $path, array $values, string $where): void
    {
        foreach ($values as $name => $value) {
            $type = $this->vocabulary()->type($name);

            if ($type === null) {
                throw ThemeException::invalidTokens($path, "{$where} sets \"{$name}\", which is not in the vocabulary; a theme adds a name under \"vocabulary\" first.");
            }

            if (! $type->accepts($value)) {
                throw ThemeException::invalidTokens($path, "{$where} sets \"{$name}\" to \"{$value}\", which is not a valid {$type->value}.");
            }
        }
    }
}
