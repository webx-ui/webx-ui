<?php

declare(strict_types=1);

return [
    'sign_in' => [
        'title' => 'Sign in',
        'remember' => 'Remember me',
        'submit' => 'Sign in',
        'no_account' => 'No account yet?',
        'register' => 'Register',
    ],
    'register' => [
        'title' => 'Create an account',
        'submit' => 'Register',
        'password_hint' => 'At least :min characters.',
        'have_account' => 'Already have an account?',
        'sign_in' => 'Sign in',
    ],
    'fields' => [
        'email' => 'E-mail',
        'password' => 'Password',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'phone' => 'Phone',
    ],
    'failed' => 'Wrong e-mail or password.',
    'blocked' => 'This account is blocked. Please contact the shop.',
    'taken' => 'This address is already registered — sign in or restore your password.',
    'value_taken' => 'This value is already taken.',
    'throttled' => 'Too many attempts. Please wait :seconds s.',
    'forgot' => [
        'link' => 'Forgot your password?',
        'title' => 'Restore the password',
        'intro' => 'Enter the e-mail of your account — we will send a link to set a new password.',
        'submit' => 'Send the link',
        'sent' => 'If there is an account with this address, we have sent a link to it.',
        'back' => 'Back to sign-in',
    ],
    'reset' => [
        'title' => 'New password',
        'password' => 'New password',
        'confirmation' => 'Repeat the password',
        'submit' => 'Save the password',
        'expired' => 'The link is out of date. Ask for a new one below.',
    ],
    'verify' => [
        'banner' => 'Please confirm your e-mail: we have sent a letter to',
        'resend' => 'Send again',
        'sent_short' => 'Sent',
        'close' => 'Close',
        'sent' => 'We have sent the letter to :email once more.',
        'already' => 'Your e-mail is already confirmed.',
        'throttled' => 'The letter has already been sent. Try again in :minutes min.',
        'sign_in' => 'Sign in',
        'continue' => 'Continue',
        'confirmed' => [
            'title' => 'E-mail confirmed',
            'text' => 'Thank you — your address is confirmed.',
        ],
        'expired' => [
            'title' => 'The link is out of date',
            'text' => 'This link no longer works. Sign in to get a new letter.',
        ],
        'unverified' => [
            'title' => 'Confirm your e-mail',
            'text' => 'This page opens after you confirm your e-mail. We have sent a letter to :email.',
        ],
    ],
    'email' => [
        'new' => 'New e-mail',
        'password' => 'Current password',
        'wrong_password' => 'The password is wrong.',
        'same' => 'This is the address you sign in with already.',
        'taken' => 'This address belongs to another account.',
        'sent' => 'We have sent a link to :email. Your address changes once you follow it.',
        'changed' => [
            'title' => 'Address changed',
            'text' => 'You now sign in with :email.',
        ],
    ],
];
