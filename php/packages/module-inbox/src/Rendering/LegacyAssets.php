<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Rendering;

/**
 * What a view of the form published before W6.4 is handed as `$assets` (an alias period, WIDGETS
 * §20 W6.4): it calls `$assets->script()` and gets the old script, the one that sends that view
 * itself. The flags stay the page's — one `<script>` of each kind however many forms ask.
 */
final readonly class LegacyAssets
{
    public function __construct(private Assets $assets) {}

    public function script(): ?string
    {
        return $this->assets->script(legacy: true);
    }

    public function captchaScript(string $provider, ?string $render = null): ?string
    {
        return $this->assets->captchaScript($provider, $render);
    }
}
