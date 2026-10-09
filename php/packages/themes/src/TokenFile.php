<?php

declare(strict_types=1);

namespace WebxUi\Themes;

/**
 * One layer's tokens.json (spec §7.3): its values, its presets and the names it adds to the
 * vocabulary. Read by `ThemeManifest::tokens()`, which checks the shape; whether the names and
 * values make sense is the vocabulary's call, made where the chain is merged.
 */
final readonly class TokenFile
{
    /**
     * @param  array<string, string>  $defaults
     * @param  array<string, array{title: string, tokens: array<string, string>}>  $presets
     * @param  array<string, TokenType>  $vocabulary
     */
    public function __construct(
        public string $path,
        public array $defaults = [],
        public array $presets = [],
        public array $vocabulary = [],
    ) {}
}
