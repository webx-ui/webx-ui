<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Path
    |---------------------------------------------------------------------------
    |
    | Where the server answers over Streamable HTTP. Null puts it next to the
    | rest of the panel's JSON, at `{webx-admin.api_path}/mcp`. Set to false
    | to serve no HTTP endpoint at all and keep the local server only.
    |
    */

    'path' => env('WEBX_MCP_PATH'),

    /*
    |---------------------------------------------------------------------------
    | Middleware and guard
    |---------------------------------------------------------------------------
    |
    | The HTTP endpoint is a door into the panel for a program, so it is closed
    | by default: `webx.mcp-auth` asks the guard below for a user and answers
    | 401 without one. The guard reads a bearer token through Passport, and the
    | token belongs to an administrator who granted an agent access to their own
    | account — see the `oauth` block.
    |
    | The guard is registered for you, over the panel's own people
    | (`webx-auth.provider`), unless the application has already defined one
    | under this name.
    |
    */

    'middleware' => ['webx.mcp-auth'],

    'guard' => env('WEBX_MCP_GUARD', 'api'),

    /*
    |---------------------------------------------------------------------------
    | How a person connects their agent
    |---------------------------------------------------------------------------
    |
    | An MCP client is given one address and nothing secret: it discovers the
    | authorization server, registers itself, sends the person to sign in, and
    | leaves with a token of their own. `laravel/mcp` writes that protocol;
    | this block is where it meets the panel.
    |
    | `enabled` is null for "whenever Passport is installed" — this package does
    | not require it, because the stdio server and the registry live without
    | one; `webx-ui/module-auth` brings it, because the model it owns is what an
    | agent acts as. A site switches it on once:
    |
    |     php artisan vendor:publish --tag=passport-migrations && php artisan migrate
    |     php artisan passport:keys
    |
    | Both are deployment steps rather than optional ones: without the keys the
    | guard cannot be built, and a call with no token answers 500 where it
    | should answer 401.
    |
    */

    'oauth' => [

        'enabled' => env('WEBX_MCP_OAUTH'),

        'prefix' => env('WEBX_MCP_OAUTH_PREFIX', 'oauth'),

        /*
        | Who Passport asks at the consent screen. Its own default is the site's
        | visitors; administrators are other people in another table behind
        | another guard, and without this the person is shown a login form for
        | an account the panel has never heard of.
        */

        'guard' => env('WEBX_MCP_OAUTH_GUARD', 'cms'),

        /*
        | Attempts, then minutes, each with a bucket of its own keyed by the
        | caller's address. Registration is open by necessity — a client has to
        | introduce itself before anybody has signed in — so it is rate limited
        | rather than guarded.
        |
        | The buckets matter as much as the numbers. Laravel's plain `throttle`
        | counts a visitor's requests to every throttled route together, which
        | is why Passport's token endpoint is moved onto its own here too:
        | sharing one counter means a flood of registrations locks
        | administrators out of the panel's sign-in form, and the other way
        | round. Null for either leaves that route as it came.
        */

        'register_throttle' => env('WEBX_MCP_OAUTH_REGISTER_THROTTLE', '10,60'),

        'token_throttle' => env('WEBX_MCP_OAUTH_TOKEN_THROTTLE', '60,1'),

        /*
        | An hour, then a month of refreshing. A site touched once a quarter
        | asks its owner to connect again, which is three clicks.
        */

        'access_token_hours' => 1,

        'refresh_token_days' => 30,

        /*
        | Where a client may be sent back with the code. These are the client's
        | addresses, not ours — a list of our own domain would let nobody in at
        | all. `laravel/mcp` ships `['*']`, and with it anybody may register a
        | client called "Site panel" that returns the code to their own server
        | and send an administrator the link; PKCE does not help there, because
        | it protects the code, not the person reading the screen.
        |
        | `localhost` is for clients that run on the machine the person is at
        | and answer the callback themselves. A client that is not on the list
        | cannot connect until it is added, which is the point.
        |
        | Null for either leaves `config('mcp.*')` alone, for a site that keeps
        | the list in its own published `config/mcp.php`.
        */

        'redirect_domains' => ['https://claude.ai', 'https://chatgpt.com', 'http://localhost'],

        'custom_schemes' => ['claude', 'cursor', 'vscode'],

    ],

    /*
    |---------------------------------------------------------------------------
    | Call log
    |---------------------------------------------------------------------------
    |
    | Every tool call, answered or refused, lands in `mcp_calls`: who the agent
    | acted as, on which connection, which tool, with what, and how it went.
    | The panel shows it as a tab next to the administrators, behind
    | `admins.audit`. Kept for `days` days and pruned nightly, the way the
    | sign-in trail is; null keeps it forever. Arguments are cut to
    | `arguments_length` characters, and anything named like a secret in them
    | is blanked before writing.
    |
    */

    'calls' => [

        'enabled' => (bool) env('WEBX_MCP_CALL_LOG', true),

        'days' => 90,

        'arguments_length' => 4000,

    ],

    /*
    |---------------------------------------------------------------------------
    | Local server
    |---------------------------------------------------------------------------
    |
    | The same server over stdio, for an agent on the machine the site runs
    | on: `php artisan mcp:start webx`. No request, no token — whoever can run
    | artisan can already do anything, the way tinker can. Null registers none.
    |
    */

    'local' => 'webx',

];
