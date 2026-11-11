<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | The languages this site is published in
    |---------------------------------------------------------------------------
    |
    | Content languages live in the `locales` table, so somebody can add one from
    | the panel without a deploy. This is only the seed a fresh installation gets
    | and the answer used while the table is not there yet — during the very first
    | `migrate`, for instance.
    |
    | The first entry is the default unless one of them says so itself.
    |
    */

    'locales' => [
        ['code' => 'en', 'default' => true],
    ],

    /*
    |---------------------------------------------------------------------------
    | Fallback
    |---------------------------------------------------------------------------
    |
    | Read when a translation is missing in the language being asked for. Keep it
    | on a language that is actually complete; a fallback nobody maintains shows
    | up as blank fields rather than as an error.
    |
    */

    'fallback' => 'en',

    /*
    |---------------------------------------------------------------------------
    | The panel's own languages
    |---------------------------------------------------------------------------
    |
    | Which languages the interface itself may be shown in — a different question
    | from which languages the site publishes. A site in Ukrainian only can still
    | have an administrator who reads the panel in English.
    |
    | Listed rather than detected, because a language is only offered here once
    | somebody has checked the translation reads well. To add one, publish the
    | packages' `lang` files, translate them, and name the code here:
    |
    |     php artisan vendor:publish --tag=webx-admin-lang
    |
    */

    'panel' => ['en', 'ru', 'uk'],

    /*
    |---------------------------------------------------------------------------
    | Where the public site's language comes from
    |---------------------------------------------------------------------------
    |
    | `prefix` reads the first segment of the path (/uk/about), `header` reads
    | Accept-Language, `none` leaves the locale alone for an application that
    | resolves it itself.
    |
    | With `prefix_default` off the default language has no prefix at all, so
    | /about and /uk/about are the same page in a site whose default is Ukrainian
    | — which is what most sites want and what search engines prefer.
    |
    */

    'strategy' => 'prefix',

    'prefix_default' => false,

    /*
    |---------------------------------------------------------------------------
    | The language of the first visit
    |---------------------------------------------------------------------------
    |
    | Somebody who comes to a page for the first time, in a language other than
    | this one, is sent to the same page in it (302), once: the cookie remembers
    | it was done, and nobody is sent anywhere again — the switcher's plain links
    | lead back to any language. Off by default (null); a language code of the
    | site turns it on. Only with the `prefix` strategy — under the others every
    | language has the same address. A page that has nothing in that language but
    | its home page is left where it is.
    |
    | Search engines, link previews and the site's own audit are never sent
    | anywhere: they are told apart by the User-Agent — a substring of it, case
    | aside. A bare `bot` is missing on purpose: it is in the name of a phone. A
    | site that lists its own replaces the whole list. `skip` — any of these in
    | the query and nothing happens; `except` — paths that are never a page
    | somebody reads. The panel is left alone without a line here.
    |
    | `offer` — later visits, already sent once and on a page of another language,
    | get a small "Switch to …?" beside the language switcher. Declining it is
    | kept in its own cookie, and it never comes back. Off by default.
    |
    */

    'first_visit' => [
        'locale' => env('WEBX_LOCALE_FIRST_VISIT'),
        'cookie' => 'webx_lang',
        'lifetime' => 60 * 24 * 365, // minutes
        'offer' => [
            'enabled' => (bool) env('WEBX_LOCALE_FIRST_VISIT_OFFER', false),
            'cookie' => 'webx_lang_offer',
        ],
        'skip' => ['no_redirect', 'preview', 'signature'],
        'except' => ['api/*', 'sitemap*.xml', 'robots.txt', 'feeds/*', '.well-known/*'],
        'bots' => [
            // Google
            'Googlebot', 'Google-InspectionTool', 'GoogleOther', 'Storebot-Google', 'AdsBot-Google',
            'Mediapartners-Google', 'APIs-Google', 'FeedFetcher-Google', 'Google-Read-Aloud',
            'Chrome-Lighthouse',
            // Other search engines
            'bingbot', 'BingPreview', 'AdIdxBot', 'msnbot', 'YandexBot', 'YandexImages',
            'YandexMobileBot', 'DuckDuckBot', 'Baiduspider', 'Applebot', 'SeznamBot', 'PetalBot',
            'Yahoo! Slurp',
            // Link previews
            'facebookexternalhit', 'Facebot', 'Twitterbot', 'LinkedInBot', 'Slackbot', 'TelegramBot',
            'WhatsApp', 'Viber', 'Discordbot', 'Pinterest', 'redditbot',
            // SEO tools and AI crawlers
            'AhrefsBot', 'SemrushBot', 'MJ12bot', 'DotBot', 'GPTBot', 'ClaudeBot', 'PerplexityBot',
            'CCBot',
            // The site's own audit (webx-audit.user_agent)
            'WebxAudit',
            // What crawlers write about themselves
            'crawler', 'spider', 'bot/', 'bot;', '+http',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Caching
    |---------------------------------------------------------------------------
    |
    | The language list and the panel's dictionary are read on nearly every
    | request and change about never. Turn this off while translating, or run
    | `php artisan webx:locales:clear` after each edit.
    |
    */

    'cache' => [
        'enabled' => env('WEBX_LOCALIZATION_CACHE', true),
        'ttl' => 86400,
        'prefix' => 'webx.localization',
    ],

    /*
    |---------------------------------------------------------------------------
    | Which translations the panel is sent
    |---------------------------------------------------------------------------
    |
    | The dictionary handed to the front end is assembled from the translation
    | namespaces registered by installed packages. Only these prefixes are
    | included — the application's own strings are its business, and shipping all
    | of them to the browser would be both wasteful and a way to leak a message
    | never meant for a client.
    |
    */

    'namespaces' => ['webx-'],

];
