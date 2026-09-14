<?php

declare(strict_types=1);

use WebxUi\Settings\Settings;

if (! function_exists('settings')) {
    /**
     * `settings('general.project-name')` — the value the site should show, in the current
     * language. Without a key, the service itself.
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key, $default);
    }
}
