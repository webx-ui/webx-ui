<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use WebxUi\Themes\Contracts\Appearance;
use WebxUi\Themes\Exceptions\ThemeException;

/**
 * The values the site's tokens end up with (spec §7.3), merged bottom up:
 *
 *     the bottom layer's defaults → … → the top layer's defaults → the chosen preset → the owner's edits
 *
 * A preset is merged down the chain the same way: a local theme may tune the base theme's
 * `night` without repeating it. What the layers ship is code, so a wrong name or a value of the
 * wrong type in a tokens.json is an exception; what the owner chose is data, so a preset that
 * does not exist or a value that does not fit is dropped and the layers' value stays.
 */
class Tokens
{
    private ?Vocabulary $vocabulary = null;

    /** @var array<string, TokenFile>|null */
    private ?array $files = null;

    /** @var array<string, string>|null */
    private ?array $values = null;

    public function __construct(
        private readonly ThemeChain $chain,
        private readonly Appearance $appearance,
    ) {}

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

        foreach ($this->files() as $file) {
            $values = [...$values, ...$file->defaults];
        }

        $values = [...$values, ...($this->presets()[$this->preset() ?? ''] ?? ['tokens' => []])['tokens']];

        $editable = $this->editable();

        foreach ($this->appearance->tokens() as $name => $value) {
            if (in_array($name, $editable, true) && $this->vocabulary()->accepts($name, $value)) {
                $values[$name] = trim($value);
            }
        }

        $ordered = [];

        foreach ($this->vocabulary()->names() as $name) {
            if (isset($values[$name])) {
                $ordered[$name] = $values[$name];
            }
        }

        return $this->values = $ordered;
    }

    public function get(string $name): ?string
    {
        return $this->values()[$name] ?? null;
    }

    /** The preset in force: the owner's choice if the chain has such a preset, otherwise none. */
    public function preset(): ?string
    {
        $preset = $this->appearance->preset();

        return $preset !== null && isset($this->presets()[$preset]) ? $preset : null;
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
     * Which tokens the owner may change: the list of the highest layer that has one (§5). Only
     * names the vocabulary knows — a list naming a token that is gone opens nothing.
     *
     * @return list<string>
     */
    public function editable(): array
    {
        foreach ($this->chain->layers as $layer) {
            if ($layer->editable !== []) {
                return array_values(array_filter($layer->editable, fn (string $name) => $this->vocabulary()->has($name)));
            }
        }

        return [];
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
