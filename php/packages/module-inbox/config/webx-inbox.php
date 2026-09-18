<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Where the public door answers
    |---------------------------------------------------------------------------
    |
    | A form on the site posts to `{path}/{slug}`. It is one prefix for every
    | form, so a site can put the whole intake behind its own firewall rule or
    | its own CDN exception with a single pattern.
    |
    | This route is deliberately outside `VerifyCsrfToken` (§2.8): a page cached
    | whole carries a token that was minted when the cache was written, and a
    | stale token answers 419 only on a live site and only after a while. The
    | session is still there, because a form without JavaScript reports its
    | errors through it.
    |
    */

    'path' => env('WEBX_INBOX_PATH', 'webx/forms'),

    /*
    |---------------------------------------------------------------------------
    | Files posted with a submission
    |---------------------------------------------------------------------------
    |
    | A disk of its own, private by default: these are a visitor's files, and
    | they have no business sitting beside the ones editors chose. Nothing ever
    | links to them directly — the panel serves the bytes itself, behind
    | `inbox.view` (§8).
    |
    | `max_size` is in kilobytes, the unit Laravel's validation speaks. The
    | types are a white list of extensions, for the same reason the media
    | library keeps one: a list of what must not be uploaded always forgets the
    | executable.
    |
    */

    'disk' => env('WEBX_INBOX_DISK', 'local'),

    'prefix' => env('WEBX_INBOX_PREFIX', 'inbox'),

    'upload' => [
        'max_size' => (int) env('WEBX_INBOX_MAX_SIZE', 10240),
        'max_files' => 10,
        'extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif',
            'pdf', 'txt', 'csv', 'rtf',
            'doc', 'docx', 'odt',
            'xls', 'xlsx', 'ods',
            'ppt', 'pptx',
            'zip',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Antispam
    |---------------------------------------------------------------------------
    |
    | Defaults for what a form does not say itself; a form may lower or raise
    | each of these in its own `options.antispam.*` (§5).
    |
    | `honeypot` is the name of the field a person never sees and a robot fills
    | in. `min_seconds` is how quickly a submission has to arrive to be a robot
    | — and note the trap in §7: on a page cached whole the hidden timestamp
    | belongs to the moment the cache was written, not to the moment somebody
    | opened the page, so a mark older than `stale_after` is not judged at all.
    | `throttle` is submissions per minute from one address to one form.
    |
    | `origins` are hosts allowed to post besides the application's own. A site
    | that serves its pages from more than one domain names the others here.
    |
    */

    'antispam' => [
        'honeypot' => env('WEBX_INBOX_HONEYPOT', 'webx_hp'),
        'timestamp' => 'webx_ts',
        'min_seconds' => 3,
        'stale_after' => 86400,
        'throttle' => 5,
        'origins' => [],
    ],

    /*
    |---------------------------------------------------------------------------
    | Captcha
    |---------------------------------------------------------------------------
    |
    | Off unless a form asks for it, because a form that cannot be submitted
    | without keys nobody has configured is a form that is quietly broken. Which
    | provider a form uses is the form's setting; the keys are the site's, and
    | they are one pair for every form — a site with `module-settings` maps them
    | here from the Integrations group rather than repeating a secret in each
    | form's options (§5).
    |
    | `key` is the public half, which the widget on the page is drawn with, and
    | `secret` the half that verifies the answer. `script` is the provider's own
    | script, printed once per page beside the first widget that needs it; a
    | site that loads it itself — or that would rather ask a mirror, which is
    | what `recaptcha.net` is for — empties or changes it here.
    |
    */

    'captcha' => [
        'recaptcha' => [
            'key' => env('WEBX_INBOX_RECAPTCHA_KEY'),
            'secret' => env('WEBX_INBOX_RECAPTCHA_SECRET'),
            'verify' => 'https://www.google.com/recaptcha/api/siteverify',
            'script' => 'https://www.google.com/recaptcha/api.js',
        ],
        'turnstile' => [
            'key' => env('WEBX_INBOX_TURNSTILE_KEY'),
            'secret' => env('WEBX_INBOX_TURNSTILE_SECRET'),
            'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
        ],
        'timeout' => 5,
    ],

    /*
    |---------------------------------------------------------------------------
    | The form on the site
    |---------------------------------------------------------------------------
    |
    | `<x-webx-form>` prints a link to one small script of its own, which turns
    | a working form into one that answers in place. Without it the form still
    | submits — it posts, and comes back with the errors or the thank-you in the
    | session — so this may be switched off by a site that bundles the published
    | copy (`php artisan vendor:publish --tag=webx-inbox-assets`) instead.
    |
    */

    'script' => (bool) env('WEBX_INBOX_SCRIPT', true),

    /*
    |---------------------------------------------------------------------------
    | Duplicates
    |---------------------------------------------------------------------------
    |
    | The same values from the same form inside this window are the same
    | submission — a double click, or a page reloaded on a POST. Outside it they
    | are a new one: without a window, the same enquiry sent again next month
    | would overwrite the first and take its date with it (§2.7).
    |
    */

    'duplicate_window' => 900,

    /*
    |---------------------------------------------------------------------------
    | Keeping submissions
    |---------------------------------------------------------------------------
    |
    | Submissions hold personal data, so `webx:inbox:prune` exists to forget it.
    | `spam_days` is how long a submission in a spam status is kept, `days` how
    | long any of them is; zero means forever, which is the default because
    | deleting a client's enquiries is not a thing to start doing by surprise.
    |
    | `anonymise_ip` drops the last octet before the address is written into the
    | submission's metadata. The antispam does not read it from there, so
    | nothing is lost but the ability to say which flat sent a thing.
    |
    */

    'prune' => [
        'spam_days' => 30,
        'days' => 0,
    ],

    'anonymise_ip' => (bool) env('WEBX_INBOX_ANONYMISE_IP', false),

];
