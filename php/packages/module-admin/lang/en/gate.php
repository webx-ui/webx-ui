<?php

declare(strict_types=1);

/*
 * The page behind the password over a site that is being tested (`webx-admin::gate`).
 *
 * It shows only when the browser's own dialog is cancelled, so it is read by someone who was
 * not given the pair or mistyped it — the one thing to say is how to get back to the dialog.
 */

return [
    'title' => 'This site is not open yet',
    'text' => 'It is being tested and asks for a name and a password. If you were given them, reload the page and enter them.',
];
