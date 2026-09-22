<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Grants;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use WebxUi\Mcp\Scopes;
use WebxUi\Mcp\Tool;

/**
 * The terms an agent was let in on, looked up from the token it called with.
 *
 * A token issued through the consent screen belongs to one administrator and one client, and
 * that pair names a {@see Grant}. The grant is what the person actually chose — "read only"
 * above all — and it is stronger than their permissions: somebody who may edit pages and
 * connected an agent to look gets an agent that looks.
 *
 * A call with no grant behind it is not refused here. Either there is no token at all (the
 * local stdio server), or the token names no client (a key for a machine), or it was granted
 * before grants were written down; none of those is this class's to judge.
 */
final class Grants
{
    /** @var array<string, Grant|null> */
    private array $found = [];

    /**
     * Why this caller may not use this tool, or null when they may.
     */
    public function refusal(?Authenticatable $user, Tool $tool): ?string
    {
        $grant = $this->forUser($user);

        if ($grant === null) {
            return null;
        }

        if ($grant->isRevoked()) {
            return 'This connection was disconnected in the panel. Ask the person to connect the agent again.';
        }

        if ($grant->read_only && $tool->mutating) {
            return 'This connection was granted read-only access, and this tool changes something. '
                .'Ask the person to connect the agent again without "read only" if they want it to write.';
        }

        return null;
    }

    /**
     * The grant behind the token this user called with, if the token names one.
     */
    public function forUser(?Authenticatable $user): ?Grant
    {
        if ($user === null) {
            return null;
        }

        $token = Scopes::tokenOf($user);

        if ($token === null) {
            return null;
        }

        $client = $token->oauth_client_id ?? $token->client_id ?? null;

        if (! is_string($client) || $client === '') {
            return null;
        }

        $key = $user->getAuthIdentifier().'|'.$client;

        if (! array_key_exists($key, $this->found)) {
            $this->found[$key] = $this->touch(Grant::query()
                ->where('cms_user_id', $user->getAuthIdentifier())
                ->where('oauth_client_id', $client)
                ->first());
        }

        return $this->found[$key];
    }

    /**
     * End a connection: the row says so, and the tokens behind it stop working.
     *
     * Both kinds of token, which is the whole point. An access token lives an hour, so
     * revoking only those would leave the agent a month of refreshing — the connection would
     * outlive the decision to end it by exactly as long as nobody was looking.
     *
     * The row is kept rather than deleted. It is what the call log points at, and a line
     * saying the connection was ended on the 21st is worth more than a gap where it was.
     */
    public function revoke(Grant $grant): void
    {
        if (! $grant->isRevoked()) {
            $grant->forceFill(['revoked_at' => Carbon::now()])->saveQuietly();
        }

        // Anything looked up earlier in this request was told the connection was live.
        $this->found = [];

        if (! class_exists(Token::class)) {
            return;
        }

        $tokens = Token::query()
            ->where('user_id', $grant->cms_user_id)
            ->where('client_id', $grant->oauth_client_id)
            ->where('revoked', false)
            ->pluck('id');

        if ($tokens->isEmpty()) {
            return;
        }

        // The refresh tokens first: they are reached through the access tokens, and there is
        // no order in which that is cheaper to do the other way round.
        RefreshToken::query()->whereIn('access_token_id', $tokens)->update(['revoked' => true]);
        Token::query()->whereIn('id', $tokens)->update(['revoked' => true]);
    }

    /**
     * "Last used" to the minute: a listing of forty tools is forty lookups, and a write for
     * each of them would be the most expensive thing the call did.
     */
    private function touch(?Grant $grant): ?Grant
    {
        if ($grant === null || $grant->isRevoked()) {
            return $grant;
        }

        $now = Carbon::now();

        if ($grant->last_used_at === null || $grant->last_used_at->lt($now->copy()->subMinute())) {
            $grant->forceFill(['last_used_at' => $now])->saveQuietly();
        }

        return $grant;
    }
}
