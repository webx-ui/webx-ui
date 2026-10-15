<?php

declare(strict_types=1);

namespace WebxUi\Auth\Keys;

use DateInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use LogicException;
use RuntimeException;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Keys\Key;
use WebxUi\Mcp\Keys\Keys;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Scopes;

/**
 * Issuing a key for a program: a personal access token of one administrator, and the row that
 * remembers it (WEBX_UI_MCP_ACCESS.md §12).
 *
 * A personal token rather than a client-credentials one, because every tool acts as
 * `$request->user()`: its permissions, the history it writes and the call log all name a
 * person, and a token with nobody behind it would be refused by the first permission check —
 * or, worse, would have to be let past all of them. The key acts as the administrator it was
 * issued for, and can never do more than they may.
 *
 * One path for the panel and for `webx:mcp:token`, so that a key made either way is listed,
 * used and revoked the same.
 */
final class KeyIssuer
{
    /** Ten years: long enough to forget about, short enough that the signature still means something. */
    public const MAX_DAYS = 3650;

    public const DEFAULT_DAYS = 365;

    public function __construct(
        private readonly ToolRegistry $tools,
        private readonly ClientRepository $clients,
        private readonly Keys $keys,
    ) {}

    /**
     * The scopes a key may be issued with: `mcp:use` — everything the administrator may do,
     * including modules installed later — and each scope an installed module declares.
     *
     * @return list<string>
     */
    public function known(): array
    {
        return [Scopes::OAUTH, ...$this->tools->scopes()];
    }

    /**
     * The scopes asked for, tidied: none, or `all`, means `mcp:use`.
     *
     * @param  list<string>  $asked
     * @return list<string>
     *
     * @throws KeyRefused
     */
    public function scopes(array $asked): array
    {
        $asked = array_values(array_unique(array_filter(
            array_map('trim', $asked),
            static fn (string $scope): bool => $scope !== '',
        )));

        if ($asked === [] || $asked === ['all']) {
            return [Scopes::OAUTH];
        }

        $known = $this->known();
        $unknown = array_values(array_diff($asked, $known));

        if ($unknown !== []) {
            throw new KeyRefused('No installed module declares '.implode(', ', $unknown).'. Known scopes: '.implode(', ', $known).'.');
        }

        // Everything already covers the rest; listing them beside it would read as a limit.
        return in_array(Scopes::OAUTH, $asked, true) ? [Scopes::OAUTH] : $asked;
    }

    /**
     * What a site has to have before Passport can sign anything, said in the words of the
     * command that fixes it. Passport's own failures name a table or a file and not the cure.
     */
    public function missingSetup(): ?string
    {
        foreach ([Passport::client()->getTable(), Passport::token()->getTable(), (new Key)->getTable()] as $table) {
            if (! Schema::hasTable($table)) {
                return "There is no [{$table}] table yet. Run `php artisan migrate` — `webx:setup` publishes Passport's migrations.";
            }
        }

        $configured = config('passport.private_key');

        if ((! is_string($configured) || $configured === '') && ! is_file(Passport::keyPath('oauth-private.key'))) {
            return 'Passport has no keys to sign a token with. Run `php artisan passport:keys`.';
        }

        return null;
    }

    /**
     * @param  list<string>  $scopes  as {@see self::scopes()} returned them
     *
     * @throws KeyRefused
     */
    public function issue(CmsUser $admin, string $name, array $scopes, int $days = self::DEFAULT_DAYS, ?CmsUser $by = null): IssuedKey
    {
        $name = trim($name);

        if ($name === '') {
            throw new KeyRefused('A key needs a name: it is what the list of keys shows.');
        }

        if ($days < 1 || $days > self::MAX_DAYS) {
            throw new KeyRefused('A key lives from 1 to '.self::MAX_DAYS.' days.');
        }

        // The door would turn the key away anyway; better to say so than hand out one that
        // answers 403.
        if (! $admin->is_active) {
            throw new KeyRefused("The administrator [{$admin->email}] is switched off.");
        }

        $trouble = $this->missingSetup();

        if ($trouble !== null) {
            throw new KeyRefused($trouble);
        }

        try {
            $provider = $admin->getProviderName();
        } catch (LogicException) {
            throw new KeyRefused('No Passport guard serves the panel\'s administrators, so a key would let nobody in. Check `webx-mcp.guard` and the `auth.guards` it names.');
        }

        $this->ensurePersonalAccessClient($provider);

        // Passport refuses a scope it has not been told about. Told here rather than for the
        // whole application: offered at large, a module scope would also be one an OAuth
        // client could ask for, and those are given `mcp:use` and nothing else.
        Passport::tokensCan(array_merge(
            Passport::scopes()->pluck('description', 'id')->all(),
            array_combine($scopes, $scopes),
        ));

        // The lifetime is signed into the token, so it is set for this one token and put back:
        // Passport reads it each time it makes the server that signs a personal token.
        $before = Passport::personalAccessTokensExpireIn();
        Passport::personalAccessTokensExpireIn(new DateInterval("P{$days}D"));

        try {
            $result = $admin->createToken($name, $scopes);
        } finally {
            Passport::personalAccessTokensExpireIn($before);
        }

        $token = $result->getToken();
        $expires = $token?->getAttribute('expires_at');

        $key = Key::query()->create([
            'cms_user_id' => $admin->getKey(),
            'created_by' => $by?->getKey(),
            'token_id' => (string) $token?->getKey(),
            'name' => $name,
            'scopes' => $scopes,
            'created_at' => Carbon::now(),
            'expires_at' => $expires,
        ]);

        return new IssuedKey($key, $result->accessToken);
    }

    /**
     * Switch off an administrator's live keys of one name — how a script that runs again
     * replaces its key rather than collecting them. Tokens issued before keys had rows of their
     * own are found by name in Passport's table.
     */
    public function revokeNamed(CmsUser $admin, string $name): void
    {
        Key::query()
            ->where('cms_user_id', $admin->getKey())
            ->where('name', $name)
            ->whereNull('revoked_at')
            ->get()
            ->each(fn (Key $key) => $this->keys->revoke($key));

        // Ids first: an update over the relation would carry its `whereHas` into the statement.
        $ids = $admin->tokens()
            ->where('name', $name)
            ->where('revoked', false)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            Passport::token()->newQuery()->whereIn('id', $ids)->update(['revoked' => true]);
        }
    }

    /**
     * Passport issues personal tokens through a client of their own, one per user provider,
     * which `passport:client --personal` would create — one more step for a site to know
     * about, and a question it would stop to ask.
     */
    private function ensurePersonalAccessClient(string $provider): void
    {
        try {
            $this->clients->personalAccessClient($provider);
        } catch (RuntimeException) {
            $this->clients->createPersonalAccessGrantClient('WebX personal access', $provider);
        }
    }
}
