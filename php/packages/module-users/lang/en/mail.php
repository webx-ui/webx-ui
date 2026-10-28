<?php

declare(strict_types=1);

return [
    'hello' => 'Hello, :name!',
    'ignore' => 'If you did not ask for it, just ignore this letter.',
    'verify' => [
        'subject' => 'Confirm your e-mail on :site',
        'text' => 'Please confirm that this is your address on :site.',
        'button' => 'Confirm the e-mail',
        'expires' => 'The link works for :hours hours.',
    ],
    'reset' => [
        'subject' => 'A new password on :site',
        'text' => 'Somebody asked to restore the password of your account on :site.',
        'button' => 'Set a new password',
        'expires' => 'The link works for :minutes minutes and only once.',
    ],
    'password-changed' => [
        'subject' => 'Your password on :site was changed',
        'text' => 'The password of your account on :site has just been changed.',
        'not-you' => 'If it was not you, restore the password at once.',
        'button' => 'Restore the password',
    ],
    'email-change' => [
        'subject' => 'Confirm the new address on :site',
        'text' => 'Follow the link to sign in to :site with :email from now on.',
        'button' => 'Confirm the address',
        'expires' => 'The link works for :minutes minutes.',
    ],
    'email-changed' => [
        'subject' => 'Your sign-in address on :site was changed',
        'text' => 'Your account on :site now signs in with :email.',
        'not-you' => 'If it was not you, restore the password and contact us.',
        'button' => 'Restore the password',
    ],
    'account-deleted' => [
        'subject' => 'Your account on :site was deleted',
        'text' => 'Your account on :site has been deleted together with its data.',
        'not-you' => 'If it was not you, contact us at once.',
    ],
];
