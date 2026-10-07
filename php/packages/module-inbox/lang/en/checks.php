<?php

declare(strict_types=1);

return [
    'inbox' => [
        'no_recipients' => [
            'title' => 'Forms that tell nobody',
            'found' => 'A switched-on form names no recipient a letter would reach: none at all, or only administrators deleted or switched off since, or addresses that are not addresses.',
            'why' => 'Every submission is saved and the visitor is thanked, but nobody hears of it until somebody happens to open the inbox — an enquiry can wait for days.',
            'fix' => 'Open the form, go to the Notifications tab and add an administrator or an address. If the form is meant to be read only in the panel, ignore this issue.',
        ],
        'notification' => [
            'title' => 'Notifications about submissions that did not leave',
            'found' => 'Letters about submissions failed, or have waited in the queue for long.',
            'why' => 'The people the form names have not heard of enquiries the panel already holds, and nothing on the site says so.',
            'fix' => 'Failed: correct the mail settings, restart the queue worker (php artisan queue:restart) so it reads them, then send the notification again from the submission. Waiting: start a queue worker.',
        ],
        'captcha_keys' => [
            'title' => 'Forms asking for a captcha the site has no keys for',
            'found' => 'A switched-on form asks for reCAPTCHA or Turnstile, and the site’s .env lacks the site key, the secret or both.',
            'why' => 'Without a site key the widget is not drawn; without a secret no answer can be verified. Either way the form refuses every submission, and visitors cannot reach you.',
            'fix' => 'Add WEBX_INBOX_RECAPTCHA_KEY and WEBX_INBOX_RECAPTCHA_SECRET (or the TURNSTILE pair) to the site’s .env, with WEBX_INBOX_RECAPTCHA_TYPE matching the kind of key, and clear the configuration cache (php artisan config:clear). Or switch the captcha off on the form’s Antispam tab.',
        ],
        'captcha_unused' => [
            'title' => 'Forms without a captcha on a site that has the keys',
            'found' => 'A switched-on form asks for no captcha, while the site has the keys for reCAPTCHA or Turnstile.',
            'why' => 'The hidden field, the timestamp and the limit per address stop most robots, so this is only a notice: the captcha is ready for the day the form draws spam.',
            'fix' => 'If the form draws spam, turn the captcha on in its Antispam tab. Otherwise ignore this issue.',
        ],
        'spam_without_captcha' => [
            'title' => 'Forms without a captcha that draw spam',
            'found' => 'A switched-on form without a captcha received submissions marked as spam, or had submissions turned away by the antispam, in the last days.',
            'why' => 'Robots have found the form. What gets through lands in the inbox and in the notifications, and the free layers alone are what they keep trying to pass.',
            'fix' => 'Turn a captcha on in the form’s Antispam tab — the site needs its keys in .env — and keep the hidden field on. Refusals are counted per day in the cache, so a cleared cache starts them from zero.',
        ],
    ],
];
