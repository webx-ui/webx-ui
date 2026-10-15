<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Cache
    |---------------------------------------------------------------------------
    |
    | The site reads settings on every request, so the whole table is kept in
    | the cache as one entry and thrown away when anything is saved.
    |
    */

    'cache' => [
        'enabled' => env('WEBX_SETTINGS_CACHE', true),
        'ttl' => 86400,
        'key' => 'webx.settings',
    ],

    /*
    |---------------------------------------------------------------------------
    | Stand-own keys
    |---------------------------------------------------------------------------
    |
    | Keys whose value belongs to the stand rather than to the site: a restore
    | of `webx:snapshot` keeps this stand's value and drops the archive's. None
    | ship today — keys and passwords live in .env — but a project that stores
    | an integration's key as a setting names it here.
    |
    */

    'stand_own' => [],

    /*
    |---------------------------------------------------------------------------
    | Tabs the site uses
    |---------------------------------------------------------------------------
    |
    | Contacts are offered in the panel only when something on the site prints
    | them. A theme says so under `settings` of its manifest, a package under
    | `extra.webx.settings`; a project that prints them from its own views says
    | so here — 'uses' => ['contacts'], or with the sections it prints:
    | 'uses' => ['contacts' => ['phones', 'hours']]. Branding is used by the
    | panel itself and needs no word here. A tab nobody uses is hidden, its
    | stored values kept: `php artisan webx:doctor` says who uses what.
    |
    */

    'uses' => [],

    /*
    |---------------------------------------------------------------------------
    | Contacts kept under keys of the site's own
    |---------------------------------------------------------------------------
    |
    | A site that had its phone or e-mail under a key of a screen patch names
    | it here — 'phones' => 'contacts.phone' — and contacts() reads it while
    | the list on the Contacts tab is empty. `php artisan webx:settings:contacts
    | --from=<key>` moves it over for good.
    |
    | `sections` are the parts of the Contacts tab the site draws: phones,
    | emails, addresses, hours (with special dates and the time zone),
    | messengers, socials. One left out is hidden from the form, so an editor
    | is not asked about what the site never shows; contacts() still returns
    | whatever is stored, and a screen patch aimed at its card still applies.
    |
    */

    'contacts' => [
        'sections' => ['phones', 'emails', 'addresses'],
        // 'sections' => ['phones', 'emails', 'addresses', 'hours', 'messengers', 'socials'],

        'legacy' => [
            // 'phones' => 'contacts.phone',
            // 'emails' => 'contacts.email',
            // 'addresses' => 'contacts.address',
        ],
    ],

];
