<?php

declare(strict_types=1);
use WebxUi\Auth\Models\CmsUser;

return [

    /*
    |---------------------------------------------------------------------------
    | Guard
    |---------------------------------------------------------------------------
    |
    | Administrators are their own kind of account, in their own table, behind
    | their own guard. A leak in the public part of the site therefore does not
    | reach the panel, and the two sets of accounts never have to agree about
    | what a "user" is.
    |
    | The guard and provider are registered for you unless the application has
    | already defined entries under these names.
    |
    */

    'guard' => 'cms',

    'provider' => 'cms_users',

    'model' => CmsUser::class,

    /*
    |---------------------------------------------------------------------------
    | Protecting the panel
    |---------------------------------------------------------------------------
    |
    | With this on, the panel's API is put behind the guard as soon as this
    | package is installed, rather than when somebody remembers to do it.
    |
    | The shell stays public on purpose: it carries no data, and it is the thing
    | that draws the login form. Everything it asks for afterwards is not public.
    |
    | Note that this *replaces* `webx-admin.api_middleware`. If you have your own
    | stack there, turn this off and add `cms.auth` yourself.
    |
    */

    'protect_panel' => true,

    // The session is what authenticates the panel, so its API runs through `web`
    // rather than the stateless `api` group.
    'panel_api_middleware' => ['web', 'cms.auth'],

    /*
    |---------------------------------------------------------------------------
    | Sign-in
    |---------------------------------------------------------------------------
    */

    'throttle' => '6,1',

    // Keep sign-in records for this many days; null keeps them forever.
    'login_log_days' => 90,

];
