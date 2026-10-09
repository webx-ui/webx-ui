<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Illuminate\Contracts\Config\Repository;
use WebxUi\Themes\Contracts\Appearance;

/** The appearance of a site without the panel's «Appearance» tab: the preset from config, no edits. */
final readonly class ConfigAppearance implements Appearance
{
    public function __construct(private Repository $config) {}

    public function preset(): ?string
    {
        $preset = $this->config->get('webx-themes.preset');

        return is_string($preset) && trim($preset) !== '' ? trim($preset) : null;
    }

    public function tokens(): array
    {
        return [];
    }
}
