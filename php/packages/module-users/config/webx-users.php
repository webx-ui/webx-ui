<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | The Blade component the public pages of the module stand in — `'layout'` for the
    | `<x-layout>` of the site's theme, which `webx:panel --sync` writes here once the site has
    | one. Empty prints the module's own bare document. The sign-in and the registration call it
    | with the handle `users.auth`; a site that wants them narrower maps the handle to
    | `layout.minimal` in `webx-compositions.layouts`.
    |
    */

    'layout' => env('WEBX_USERS_LAYOUT'),

    /*
    |--------------------------------------------------------------------------
    | Layout of the account
    |--------------------------------------------------------------------------
    |
    | The pages of the account stand in the composition `layout.account` (the site's layout with
    | the menu of the account beside the content, COMPOSITIONS §3.7) when the site has a layout,
    | and in the bare document when it has none. A Blade component named here wins over both.
    |
    */

    'account_layout' => env('WEBX_USERS_ACCOUNT_LAYOUT'),

    /*
    |--------------------------------------------------------------------------
    | Guard
    |--------------------------------------------------------------------------
    |
    | The guard visitors sign in with. It is added to `auth.guards` unless the site declared it
    | itself; the site's default guard (`web`) is never touched, so `auth()->user()` in the site's
    | own code does not become a visitor without anybody asking.
    |
    */

    'guard' => 'site',

    /*
    |--------------------------------------------------------------------------
    | Prefix
    |--------------------------------------------------------------------------
    |
    | Where the pages live: `/account/sign-in`, `/account/register`. Inside the language prefix
    | like every other address of the site.
    |
    */

    'prefix' => env('WEBX_USERS_PREFIX', 'account'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web', 'webx.locale'],

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    |
    | `open` — anybody registers; `closed` — the page of registration answers 404 and its links
    | disappear, signing in still works (a site that lets people in by invitation only).
    |
    */

    'registration' => env('WEBX_USERS_REGISTRATION', 'open'),

    /*
    |--------------------------------------------------------------------------
    | Passwords
    |--------------------------------------------------------------------------
    |
    | The least a password must be. A site makes it stricter here: `mixed`, `numbers`,
    | `symbols`. `uncompromised` asks an outside service about every new password and is off.
    |
    */

    'password_rules' => [
        'min' => 8,
        'mixed' => false,
        'numbers' => false,
        'symbols' => false,
        'uncompromised' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Remember me
    |--------------------------------------------------------------------------
    |
    | Whether the sign-in offers «Remember me». The cookie lives as long as Laravel's remember
    | cookie does (`auth.guards.*.remember`, minutes; five years by default).
    |
    */

    'remember' => true,

    /*
    |--------------------------------------------------------------------------
    | Throttle
    |--------------------------------------------------------------------------
    |
    | How many attempts before a 429 (spec §15). `sign_in` — per address and IP a minute;
    | `sign_in_ip` — per IP a minute, whatever the address; `register` — per IP an hour.
    | `forgot` / `forgot_ip` — requests of a reset link per address and IP / per IP an hour (over
    | them no letter leaves and the answer stays the same); `reset` — new passwords per IP a
    | minute; `verify_minute` / `verify_hour` — letters of confirmation per visitor a minute / an
    | hour; `email_change` — letters to a new address per visitor an hour.
    |
    */

    'throttle' => [
        'sign_in' => 5,
        'sign_in_ip' => 30,
        'register' => 5,
        'forgot' => 3,
        'forgot_ip' => 20,
        'reset' => 10,
        'verify_minute' => 1,
        'verify_hour' => 5,
        'email_change' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Links in letters
    |--------------------------------------------------------------------------
    |
    | How long they work. `reset_minutes` — the link of «Forgot your password?», one per visitor,
    | gone once used (spec §7); `verify_hours` — the link that confirms the address (§8.1);
    | `email_minutes` — the link that moves the sign-in to a new address (§8.5).
    |
    */

    'reset_minutes' => 60,

    'verify_hours' => 72,

    'email_minutes' => 60,

];
