<?php

declare(strict_types=1);

namespace WebxUi\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A social network linked to a visitor (spec §3 `users_identities`): the provider's id of the
 * person and the address it gave, for the account's list. No token of the provider is kept — the
 * module needs only to know it is the same person. The id is never shown outside the module.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_user_id
 * @property string|null $email
 * @property Carbon|null $created_at
 * @property Carbon|null $last_used_at
 */
class Identity extends Model
{
    public const null UPDATED_AT = null;

    protected $table = 'users_identities';

    protected $fillable = ['user_id', 'provider', 'provider_user_id', 'email', 'last_used_at'];

    protected $hidden = ['provider_user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
