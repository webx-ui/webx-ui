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
    | The HTTP endpoint is a door into the panel for a program, so it is
    | closed by default: `webx.mcp-auth` asks the guard below for a user and
    | answers 401 without one. The guard is Sanctum's, which reads a bearer
    | token from the Authorization header — `php artisan webx:mcp:token`
    | issues one to an administrator, with the scopes as its abilities.
    |
    */

    'middleware' => ['webx.mcp-auth'],

    'guard' => 'sanctum',

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
