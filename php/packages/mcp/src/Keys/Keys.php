<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Keys;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use WebxUi\Mcp\Scopes;

/**
 * The keys for programs, looked up from the token a call came in with.
 *
 * Issuing one is the auth module's business — it owns the administrators and Passport. What
 * lives here is what the door needs on every call: which key this is, that it was used, and
 * whether the caller is a program at all.
 */
final class Keys
{
    /** @var array<string, Key|null> */
    private array $found = [];

    /**
     * The key behind the token this user called with, if the token is one. Its `last_used_at`
     * moves on the way.
     */
    public function forUser(?Authenticatable $user): ?Key
    {
        $id = self::tokenId($user);

        if ($id === null) {
            return null;
        }

        if (! array_key_exists($id, $this->found)) {
            $this->found[$id] = $this->touch($this->lookup($id));
        }

        return $this->found[$id];
    }

    /**
     * Whether this call came from a program rather than a person.
     *
     * A key issued here, or any personal access token — one issued before keys had rows of
     * their own is no less a program's. A person is somebody signed in to the panel or an agent
     * they let in on the consent screen; a key speaks for nobody, whatever scopes it carries,
     * so a module's "only a person may" holds against every key alike.
     */
    public function isKey(?Authenticatable $user): bool
    {
        if ($this->forUser($user) !== null) {
            return true;
        }

        $token = $user === null ? null : Scopes::tokenOf($user);
        $client = $token === null ? null : ($token->oauth_client_id ?? $token->client_id ?? null);

        if (! is_string($client) || $client === '' || ! class_exists(Passport::class)) {
            return false;
        }

        $found = Passport::client()->newQuery()->find($client);

        return $found instanceof Client && $found->hasGrantType('personal_access');
    }

    /**
     * Switch a key off. The row stays: the call log points at it, and "revoked on the 15th by
     * Anna" is worth more than a gap where the key used to be.
     */
    public function revoke(Key $key): void
    {
        if (! $key->isRevoked()) {
            $key->forceFill(['revoked_at' => Carbon::now()])->saveQuietly();
        }

        $this->found = [];

        if (class_exists(Token::class)) {
            Token::query()->whereKey($key->token_id)->update(['revoked' => true]);
        }
    }

    private static function tokenId(?Authenticatable $user): ?string
    {
        $token = $user === null ? null : Scopes::tokenOf($user);

        if ($token === null) {
            return null;
        }

        // Passport 13 hands the guard an `AccessToken` built from the signed claims, not the
        // table row; older shapes and test doubles answer `getKey()`.
        $id = $token->oauth_access_token_id ?? (method_exists($token, 'getKey') ? $token->getKey() : null);

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * The row, or nothing on a site that has the code but not yet the table: this runs on every
     * call, and a package updated before `migrate` must not turn every agent away. Such a site
     * has no keys of its own yet, and a personal token is still told apart by its client.
     */
    private function lookup(string $id): ?Key
    {
        try {
            return Key::query()->where('token_id', $id)->first();
        } catch (QueryException) {
            return null;
        }
    }

    /** To the minute, for the reason `Grants::touch()` gives. */
    private function touch(?Key $key): ?Key
    {
        if ($key === null || $key->isRevoked()) {
            return $key;
        }

        $now = Carbon::now();

        if ($key->last_used_at === null || $key->last_used_at->lt($now->copy()->subMinute())) {
            $key->forceFill(['last_used_at' => $now])->saveQuietly();
        }

        return $key;
    }
}
