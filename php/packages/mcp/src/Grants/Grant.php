<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Grants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One agent an administrator let in, and on what terms.
 *
 * Passport keeps the tokens; this keeps what Passport cannot say. "Read only" is not a scope
 * the client asked for, `last_used_at` is something Passport never writes, and the consent —
 * which text the person saw and when they pressed Allow — has to be kept somewhere that a
 * refreshed token does not overwrite.
 *
 * @property int $id
 * @property int $cms_user_id
 * @property string $oauth_client_id
 * @property string $client_name
 * @property string $redirect_host
 * @property bool $read_only
 * @property string $consent_version
 * @property Carbon|null $created_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 */
class Grant extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'mcp_grants';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_only' => 'boolean',
            'created_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
