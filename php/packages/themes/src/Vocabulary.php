<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use WebxUi\Themes\Exceptions\ThemeException;

/**
 * The names a site's style is written in (spec §7.2) — one list for every theme, module and
 * block, so that `--site-color-accent` means the same thing wherever it is read. On the page a
 * token is `--site-<name>`.
 *
 * Colours are roles, not shades: a corporate site has three to five brand colours, and a
 * vocabulary with one accent ends with a theme inventing `--site-olive` and blocks that no
 * longer move between sites.
 *
 * A theme may add names of its own under `vocabulary` in its tokens.json, but not one the engine
 * already has: a block written against the engine's `gutter` has to get a length whatever the theme.
 */
final readonly class Vocabulary
{
    /**
     * @param  array<string, TokenType>  $tokens  In the order the page prints them.
     */
    public function __construct(public array $tokens) {}

    /** The engine's own list. Adding a name is a minor change of the package; removing one breaks themes. */
    public static function base(): self
    {
        $tokens = [];

        foreach (['bg', 'surface', 'surface-muted', 'text', 'text-muted', 'border', 'accent', 'accent-contrast',
            'accent-2', 'highlight', 'danger', 'success'] as $color) {
            $tokens['color-'.$color] = TokenType::Color;
        }

        $tokens['font-body'] = TokenType::Font;
        $tokens['font-heading'] = TokenType::Font;
        $tokens['font-size-base'] = TokenType::Length;
        $tokens['line-height-base'] = TokenType::Number;

        foreach (range(1, 6) as $step) {
            $tokens['font-size-'.$step] = TokenType::Length;
        }

        foreach (range(1, 8) as $step) {
            $tokens['space-'.$step] = TokenType::Length;
        }

        foreach (['sm', 'md', 'lg', 'full'] as $radius) {
            $tokens['radius-'.$radius] = TokenType::Length;
        }

        $tokens['shadow-sm'] = TokenType::Shadow;
        $tokens['shadow-md'] = TokenType::Shadow;
        $tokens['container-width'] = TokenType::Length;
        $tokens['gutter'] = TokenType::Length;
        $tokens['duration'] = TokenType::Time;
        $tokens['easing'] = TokenType::Easing;

        return new self($tokens);
    }

    /**
     * The engine's list and what every layer of the chain adds to it, bottom layer first. Two
     * layers may declare the same name if they agree on its type.
     */
    public static function forChain(ThemeChain $chain): self
    {
        $tokens = self::base()->tokens;
        $base = $tokens;

        foreach (array_reverse($chain->layers) as $layer) {
            $file = $layer->tokens();

            foreach ($file->vocabulary as $name => $type) {
                if (isset($base[$name])) {
                    throw ThemeException::invalidTokens($file->path, "\"{$name}\" is already in the engine's vocabulary and cannot be declared again.");
                }

                if (isset($tokens[$name]) && $tokens[$name] !== $type) {
                    throw ThemeException::invalidTokens($file->path, "\"{$name}\" is declared as {$type->value}, but a layer below declared it as {$tokens[$name]->value}.");
                }

                $tokens[$name] = $type;
            }
        }

        return new self($tokens);
    }

    public function has(string $name): bool
    {
        return isset($this->tokens[$name]);
    }

    public function type(string $name): ?TokenType
    {
        return $this->tokens[$name] ?? null;
    }

    /** A known name with a value of its type — the one test a value passes on its way in and out. */
    public function accepts(string $name, string $value): bool
    {
        return $this->type($name)?->accepts($value) ?? false;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->tokens);
    }
}
