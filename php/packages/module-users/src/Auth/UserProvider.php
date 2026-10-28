<?php

declare(strict_types=1);

namespace WebxUi\Users\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Users\Models\User;

/**
 * The provider of the guard `site`: Eloquent over {@see User}, but a blocked visitor is nobody.
 *
 * The session and the remember cookie name the visitor by id on every request; answering null
 * for a blocked one is what ends the sessions that were open when the block was set (spec §6.3),
 * on their next request and without a table of sessions to search.
 */
final class UserProvider extends EloquentUserProvider
{
    /** @return (Authenticatable&Model)|null */
    public function retrieveById($identifier): ?Authenticatable
    {
        return self::unlessBlocked(parent::retrieveById($identifier));
    }

    /** @return (Authenticatable&Model)|null */
    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return self::unlessBlocked(parent::retrieveByToken($identifier, $token));
    }

    /**
     * @param  (Authenticatable&Model)|null  $user
     * @return (Authenticatable&Model)|null
     */
    private static function unlessBlocked(?Authenticatable $user): ?Authenticatable
    {
        return $user instanceof User && $user->isBlocked() ? null : $user;
    }
}
