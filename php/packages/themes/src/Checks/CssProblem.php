<?php

declare(strict_types=1);

namespace WebxUi\Themes\Checks;

/**
 * What a stylesheet of the chain may not do (spec §8) — each a value the presets and the panel
 * cannot reach, so the page stops repainting on the part of it that holds one.
 */
enum CssProblem: string
{
    case LiteralColor = 'literal-color';
    case LiteralFont = 'literal-font';
    case UnknownToken = 'unknown-token';
    case Fallback = 'token-fallback';
    case MediaWidth = 'media-width';
    case MediaScheme = 'media-scheme';

    /** What it is, in a few words: the second column of `webx:theme:check`. */
    public function what(): string
    {
        return match ($this) {
            self::LiteralColor => 'literal colour',
            self::LiteralFont => 'literal font family',
            self::UnknownToken => 'token not in the vocabulary',
            self::Fallback => 'fallback inside var()',
            self::MediaWidth => '@media by width',
            self::MediaScheme => '@media by colour scheme',
        };
    }

    public function why(): string
    {
        return match ($this) {
            self::LiteralColor => 'no preset and no change in tokens.json repaints it; use a colour role, var(--site-color-…)',
            self::LiteralFont => 'the owner\'s font never reaches it; use var(--site-font-body) or var(--site-font-heading), or a generic family',
            self::UnknownToken => 'a variable nobody declares is an empty value: no colour, no border, no error',
            self::Fallback => 'the fallback is a literal value no preset repaints; every token of the vocabulary has a value',
            self::MediaWidth => 'width is the container\'s to decide: use @container',
            self::MediaScheme => 'the engine prints the dark values and honours data-theme on <html>; put them under schemes.dark in tokens.json',
        };
    }
}
