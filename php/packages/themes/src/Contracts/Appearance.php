<?php

declare(strict_types=1);

namespace WebxUi\Themes\Contracts;

/**
 * What the site's owner chose for the theme (spec §7.4): a preset and the values of the tokens
 * the theme lets them edit. Without the panel it is the config — `webx-themes.preset` and no
 * edits; the «Appearance» tab binds its own implementation over the `appearance` setting.
 *
 * Nothing here is trusted: a preset the chain does not have is ignored, and a value is used
 * only if its token is editable and the value is of the token's type.
 */
interface Appearance
{
    public function preset(): ?string;

    /** @return array<string, string> */
    public function tokens(): array;
}
