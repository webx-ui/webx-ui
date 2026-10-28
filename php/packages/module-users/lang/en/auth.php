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
    'throttled' => 'Too many attempts. Please wait :seconds s.',
];
