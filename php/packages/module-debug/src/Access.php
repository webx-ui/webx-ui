<?php

declare(strict_types=1);

namespace WebxUi\Debug;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Cookie\QueueingFactory as Cookies;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Debug\Panel\DebugModule;

/**
 * Who may see a report (§3 of the debug bar spec): an active administrator of the panel's own
 * guard, with `debug.view`.
 *
 * Two ways to be that person on a page of the site. A browser carries the session, and the
 * cookie {@see self::COOKIE} only says «start collecting early» — it is checked against the
 * session when the response is decided, so a forged one starts nothing that is ever kept. The
 * MCP tool `debug_page` carries a one-time token instead (§13): signed with the application key,
 * good for a minute, naming the administrator the agent acts for and the id the report goes
 * under, and refused the second time it is shown.
 */
final class Access
{
    /** The cookie that says the next page is worth collecting from its first middleware. */
    public const COOKIE = 'wx_debug';

    /** The header the MCP tool sends its token in. */
    public const HEADER = 'X-Webx-Debug';

    /** How long a token is good for, in seconds. */
    private const TOKEN_TTL = 60;

    public function __construct(
        private readonly Config $config,
        private readonly AuthFactory $auth,
        private readonly Container $container,
        private readonly CacheFactory $cache,
        private readonly Cookies $cookies,
    ) {}

    /**
     * Taken when a token is made or read, not when the bar boots: the provider builds this class
     * while the application starts, and a site without `APP_KEY` yet (`webx:setup`, `key:generate`,
     * static analysis) has to start all the same.
     */
    private function encrypter(): StringEncrypter
    {
        return $this->container->make(StringEncrypter::class);
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('webx-debug.enabled', true);
    }

    /**
     * The administrator signed in to the panel in this session, if any — without a query unless
     * somebody is.
     *
     * @return (Model&HasPermissions)|null
     */
    public function administrator(): ?Model
    {
        $guard = $this->guard();

        if ($guard === null) {
            return null;
        }

        $user = $this->auth->guard($guard)->user();

        return $user instanceof Model && $user instanceof HasPermissions && $this->active($user) ? $user : null;
    }

    /** Whether somebody may see reports at all: active, with the permission. */
    public function allows(?Model $user): bool
    {
        return $user instanceof HasPermissions && $this->active($user) && $user->hasPermission(DebugModule::PERMISSION);
    }

    /**
     * The cookie for an administrator who may see reports: encrypted on its way out like every
     * other, readable by no script, as long as the session lives.
     */
    public function mark(Model $user): Cookie
    {
        return $this->cookies->make(self::COOKIE, (string) $user->getKey(), (int) $this->config->get('session.lifetime', 120), null, null, null, true, false, 'lax');
    }

    public function unmark(): Cookie
    {
        return $this->cookies->forget(self::COOKIE);
    }

    /** The guard of the panel, when the site has one to name. */
    public function guard(): ?string
    {
        $guard = $this->config->get('webx-auth.guard');

        return is_string($guard) && $guard !== '' && $this->config->get("auth.guards.{$guard}") !== null ? $guard : null;
    }

    /**
     * A token for one request of `debug_page`: the report id is chosen here, so the tool knows
     * where to read the report once the page has answered.
     *
     * @return array{token: string, id: string}
     */
    public function issue(Model $user): array
    {
        $id = (string) Str::ulid();

        $token = $this->encrypter()->encryptString((string) json_encode([
            'user' => $user->getKey(),
            'id' => $id,
            'nonce' => Str::random(24),
            'expires' => time() + self::TOKEN_TTL,
        ]));

        return ['token' => $token, 'id' => $id];
    }

    /**
     * Who a token speaks for and the report id it carries — once. A forged, expired or second
     * use is null.
     *
     * @return array{user: Model&HasPermissions, id: string}|null
     */
    public function redeem(string $token): ?array
    {
        try {
            $payload = json_decode($this->encrypter()->decryptString($token), true, 4, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload) || ! is_int($payload['expires'] ?? null) || $payload['expires'] < time()) {
            return null;
        }

        $nonce = $payload['nonce'] ?? null;
        $id = $payload['id'] ?? null;

        if (! is_string($nonce) || ! is_string($id) || ! Reports::isId($id)) {
            return null;
        }

        // Used once: the first redemption writes the nonce down, the second finds it there.
        $store = $this->config->get('webx-debug.store');

        if (! $this->cache->store(is_string($store) && $store !== '' ? $store : null)->add('webx:debug:nonce:'.$nonce, true, self::TOKEN_TTL * 2)) {
            return null;
        }

        $user = $this->user($payload['user'] ?? null);

        return $user !== null && $this->allows($user) ? ['user' => $user, 'id' => $id] : null;
    }

    /** @return (Model&HasPermissions)|null */
    private function user(mixed $key): ?Model
    {
        $guard = $this->guard();

        if ($guard === null || (! is_int($key) && ! is_string($key))) {
            return null;
        }

        $provider = $this->auth->guard($guard);
        $user = method_exists($provider, 'getProvider') ? $provider->getProvider()->retrieveById($key) : null;

        return $user instanceof Model && $user instanceof HasPermissions ? $user : null;
    }

    private function active(Model $user): bool
    {
        $active = $user->getAttribute('is_active');

        return $active !== false && $active !== 0;
    }
}
