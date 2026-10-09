<?php

declare(strict_types=1);

use WebxUi\Settings\Contacts\Contacts;
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

if (! function_exists('contacts')) {
    /**
     * `contacts()->primaryPhone()`, `contacts()->hours()->openNow()` — the "Contacts" tab of the
     * settings, read as phones, addresses, hours and channels (WIDGETS §12.1).
     */
    function contacts(): Contacts
    {
        return app(Contacts::class);
    }
}
