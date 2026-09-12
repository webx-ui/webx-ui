<?php

declare(strict_types=1);

namespace WebxUi\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One attempt to sign in, successful or not.
 *
 * @property int $id
 * @property int|null $cms_user_id
 * @property string $email
 * @property bool $successful
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
class LoginRecord extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cms_login_records';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CmsUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }
}
