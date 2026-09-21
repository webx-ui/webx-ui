<?php

declare(strict_types=1);

namespace WebxUi\Auth\Consent;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Passport\Client;
use WebxUi\Admin\Contracts\BrandingSource;
use WebxUi\Admin\Manifest\Branding;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;

/**
 * The page a person sees when their agent asks to be let in.
 *
 * Passport hands over the client, the person and a token for the form; this turns them into
 * what the person needs to decide: who is asking, where the answer goes, and what the agent
 * will be able to do — in the words of the panel's modules rather than in scopes, because
 * `pages:write` tells nobody anything. And the warning, which is the part that is kept.
 */
final class ConsentScreen
{
    /**
     * Which text the person agreed to. Bumped whenever the warning's wording changes, so that
     * a grant on file says what its holder was actually shown.
     */
    public const VERSION = '2026-09-21';

    public function __construct(
        private readonly ResponseFactory $responses,
        private readonly ModuleRegistry $modules,
        private readonly Container $container,
        private readonly Config $config,
    ) {}

    /**
     * What `Passport::authorizationView()` calls, with the parameters Passport passes to it.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function __invoke(array $parameters): Response
    {
        /** @var Client $client */
        $client = $parameters['client'];
        /** @var CmsUser $user */
        $user = $parameters['user'];
        /** @var Request $request */
        $request = $parameters['request'];

        $redirect = $request->query('redirect_uri');
        $redirect = is_string($redirect) && $redirect !== '' ? $redirect : (string) ($client->redirect_uris[0] ?? '');

        return $this->responses->view('webx-auth::consent', [
            'client' => $client,
            'user' => $user,
            'authToken' => $parameters['authToken'],
            'site' => $request->getHost(),
            'returnTo' => self::host($redirect),
            'here' => $request->fullUrl(),
            'brand' => $this->brand(),
            'rights' => $this->rights($user),
        ]);
    }

    /**
     * Where the answer goes, as a person reads an address: the host for a web client, and
     * the scheme with it for one that opens an application (`cursor://…`), because there
     * the scheme is the name.
     */
    public static function host(string $redirect): string
    {
        $parts = parse_url($redirect);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return $redirect;
        }

        $scheme = $parts['scheme'] ?? '';

        return in_array($scheme, ['http', 'https', ''], true)
            ? $parts['host']
            : $scheme.'://'.$parts['host'];
    }

    /**
     * What the agent will be able to do, module by module: `view` where the person may only
     * look, `edit` where any of their permissions in the module goes further than that. Only
     * the modules that offer an agent anything — a section without tools is not something
     * the agent can reach, whatever the person may do there by hand.
     *
     * Null for a super administrator: their agent can do everything, including what no list
     * would name, and a list would be a lie.
     *
     * @return list<array{module: string, level: 'view'|'edit'}>|null
     */
    public function rights(CmsUser $user): ?array
    {
        if ($user->is_super) {
            return null;
        }

        $held = $user->permissions();
        $rights = [];

        foreach ($this->modules->all() as $module) {
            if (! $module instanceof ProvidesMcpTools) {
                continue;
            }

            $mine = array_values(array_intersect($module->permissions(), $held));

            if ($mine === []) {
                continue;
            }

            $rights[] = [
                'module' => $module->title(),
                'level' => self::edits($mine) ? 'edit' : 'view',
            ];
        }

        return $rights;
    }

    /**
     * @param  list<string>  $permissions
     */
    private static function edits(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! str_ends_with($permission, '.view')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The site's own name and logo, from whoever supplies them to the panel; without a
     * source, the configured title.
     */
    private function brand(): Branding
    {
        $brand = $this->container->bound(BrandingSource::class)
            ? $this->container->make(BrandingSource::class)->branding()
            : new Branding;

        $title = trim($brand->title ?? '');

        return $title === ''
            ? new Branding((string) $this->config->get('webx-admin.title'), $brand->logo, $brand->mark)
            : $brand;
    }
}
