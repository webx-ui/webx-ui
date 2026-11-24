<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use WebxUi\Mcp\Keys\Key;
use WebxUi\Tasks\Runner\SessionRole;

/**
 * A key issued for one stage and one session (§14.4): the agent's name is here, not in the
 * session's arguments, and the key is revoked when the stage moves on.
 *
 * @property int $id
 * @property int $key_id
 * @property int $stage_id
 * @property SessionRole $role
 * @property string $agent
 * @property Carbon|null $created_at
 * @property Carbon|null $revoked_at
 */
class SessionKey extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'tasks_session_keys';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => SessionRole::class,
            'created_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Key, $this>
     */
    public function key(): BelongsTo
    {
        return $this->belongsTo(Key::class, 'key_id');
    }

    /**
     * @return BelongsTo<Stage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'stage_id');
    }
}
