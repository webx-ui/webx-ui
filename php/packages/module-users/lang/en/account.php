<?php

declare(strict_types=1);

return [
    'menu' => [
        'label' => 'Account',
        'profile' => 'Profile',
        'security' => 'Sign-in and security',
        'sign_out' => 'Sign out',
    ],
    'profile' => [
        'title' => 'Profile',
        'personal' => 'Personal data',
        'save' => 'Save',
        'saved' => 'Saved.',
    ],
    'email' => [
        'title' => 'E-mail',
        'confirmed' => 'confirmed',
        'unconfirmed' => 'not confirmed',
        'pending' => 'Waiting for confirmation: :email — follow the link in the letter.',
        'submit' => 'Change the e-mail',
    ],
    'security' => [
        'title' => 'Sign-in and security',
    ],
    'password' => [
        'title' => 'Password',
        'current' => 'Current password',
        'new' => 'New password',
        'confirmation' => 'Repeat the new password',
        'submit' => 'Change the password',
        'changed' => 'The password is changed. Other devices are signed out.',
        'none' => 'Your account has no password yet. We will send a link to set one to your e-mail.',
        'set' => 'Set a password',
        'link_sent' => 'We have sent a link to :email.',
    ],
    'sessions' => [
        'title' => 'Sign out everywhere',
        'text' => 'End the sessions on every other device and browser. This one stays signed in.',
        'submit' => 'Sign out everywhere',
        'done' => 'You are signed out on every other device.',
    ],
    'delete' => [
        'title' => 'Delete the account',
        'text' => 'The account and everything it keeps are deleted for good. It cannot be restored.',
        'password' => 'Current password',
        'email' => 'Your e-mail',
        'email_hint' => 'Type the address of the account to confirm.',
        'wrong_email' => 'This is not the address of the account.',
        'submit' => 'Delete forever',
    ],
];
