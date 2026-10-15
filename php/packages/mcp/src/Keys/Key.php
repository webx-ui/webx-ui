<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Keys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A key for a program: a personal token of one administrator, issued without anybody pressing
 * Allow (WEBX_UI_MCP_ACCESS.md §12).
 *
 * Passport keeps the token; this keeps what Passport cannot say — who issued it, when it was
 * last used, and that it was revoked on purpose rather than simply having run out.
 *
 * @property int $id
 * @property int $cms_user_id
 * @property int|null $created_by
 * @property string $token_id
 * @property string $name
 * @property list<string> $scopes
 * @property Carbon|null $created_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 */
class Key extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'mcp_keys';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** `live`, `expired` or `revoked` — revoked wins, because it was somebody's decision. */
    public function state(): string
    {
        return match (true) {
            $this->isRevoked() => 'revoked',
            $this->isExpired() => 'expired',
            default => 'live',
        };
    }
}
