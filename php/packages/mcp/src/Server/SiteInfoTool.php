<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Server;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request as HttpRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool as McpTool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use WebxUi\Mcp\Grants\Grants;

/**
 * `site_info`: which site this connection is to, and as whom.
 *
 * Not a module's tool, so it is served past the registry: it is behind no permission and no
 * scope, because anybody who may connect at all may know where they connected — and an
 * agent that can tell two sites apart only by asking must always be able to ask. A connection
 * the person disconnected is the one exception: it is refused everything, this included.
 */
#[IsReadOnly]
#[IsIdempotent]
final class SiteInfoTool extends McpTool
{
    protected string $name = 'site_info';

    protected string $title = 'Site Info';

    protected string $description = 'Which site this connection is to: its address, name and environment '
        .'(`production` is the live site visitors see), the WebX UI version, and the administrator you act as. '
        .'Several sites can be connected at once with the very same tools — call this before the first change '
        .'and check the address against the site the person named.';

    public function shouldRegister(HttpRequest $request, Grants $grants): bool
    {
        return ! ($grants->forUser($request->user())?->isRevoked() ?? false);
    }

    public function handle(Request $request, Grants $grants): Response|ResponseFactory
    {
        if ($grants->forUser($request->user())?->isRevoked() ?? false) {
            return Response::error('This connection was disconnected in the panel. Ask the person to connect the agent again.');
        }

        return Response::structured([
            'site' => Site::name(),
            'url' => Site::url(),
            'environment' => Site::environment(),
            'webx_version' => WebxServer::packageVersion(),
            'acting_as' => self::person($request->user()),
        ]);
    }

    /**
     * @return array{id: mixed, name: ?string, email: ?string}|null
     */
    private static function person(?Authenticatable $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $read = static function (string $attribute) use ($user): ?string {
            $value = method_exists($user, 'getAttribute') ? $user->getAttribute($attribute) : ($user->{$attribute} ?? null);

            return is_string($value) ? $value : null;
        };

        return ['id' => $user->getAuthIdentifier(), 'name' => $read('name'), 'email' => $read('email')];
    }
}
