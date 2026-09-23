<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests\Fixtures;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * An administrator as Passport presents one, with Passport's own trait rather than an
 * imitation of it: the shape `Scopes` reads is the real thing, so a change in Passport shows
 * up here rather than on a site.
 *
 * Nothing is ever saved — the token is set by hand, the way `Passport::actingAs()` sets it.
 */
final class PassportUser extends Model implements OAuthenticatable
{
    use Authenticatable;
    use HasApiTokens;

    protected $table = 'cms_users';

    protected $guarded = [];

    /**
     * @param  list<string>  $scopes
     * @param  string|null  $client  the client the token was issued to, as a token granted through consent names it
     */
    public static function bearing(array $scopes, ?string $client = null): self
    {
        $user = new self(['id' => 1, 'name' => 'Agent']);

        $user->withAccessToken(new AccessToken(array_filter([
            'oauth_user_id' => 1,
            'oauth_scopes' => $scopes,
            'oauth_client_id' => $client,
        ])));

        return $user;
    }
}
