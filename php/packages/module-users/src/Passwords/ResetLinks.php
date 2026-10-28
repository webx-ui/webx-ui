<?php

declare(strict_types=1);

namespace WebxUi\Users\Passwords;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use WebxUi\Users\Models\User;

/**
 * The links of «Forgot your password?» (spec §3, §7): one live link per visitor, a new one
 * replaces it, the link works once and for `webx-users.reset_minutes`.
 *
 * The token is 32 random bytes in base64url and lives only in the letter; the table keeps its
 * sha256, so a copy of the database opens no account. A token is looked up by its hash alone —
 * nothing in the link names the visitor.
 */
final readonly class ResetLinks
{
    public const string TABLE = 'users_password_resets';

    public const string RESET = 'reset';

    public const string INVITE = 'invite';

    public function __construct(private ConnectionInterface $db) {}

    /** A new link for the visitor, in place of any they had; the token to put in the letter. */
    public function issue(User $user, string $purpose = self::RESET, ?int $minutes = null): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = Date::now();

        $this->db->table(self::TABLE)->upsert([[
            'user_id' => $user->getKey(),
            'token_hash' => self::hash($token),
            'purpose' => $purpose,
            'expires_at' => $now->copy()->addMinutes($minutes ?? self::minutes()),
            'created_at' => $now,
        ]], ['user_id'], ['token_hash', 'purpose', 'expires_at', 'created_at']);

        return $token;
    }

    /** The visitor a live token belongs to, or null: unknown, used, expired, of a blocked account. */
    public function find(string $token): ?User
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }

        $row = $this->db->table(self::TABLE)
            ->where('token_hash', self::hash($token))
            ->where('expires_at', '>', Date::now())
            ->first(['user_id']);

        if ($row === null) {
            return null;
        }

        $user = User::query()->find($row->user_id);

        return $user instanceof User && ! $user->isBlocked() ? $user : null;
    }

    /** Gone: used, the password changed some other way, the address moved (spec §3). */
    public function forget(User|int $user): void
    {
        $this->db->table(self::TABLE)->where('user_id', $user instanceof User ? $user->getKey() : $user)->delete();
    }

    /** The expired links, for `webx:users:prune`. */
    public function expired(?Carbon $now = null): Builder
    {
        return $this->db->table(self::TABLE)->where('expires_at', '<=', $now ?? Date::now());
    }

    public static function minutes(): int
    {
        $minutes = config('webx-users.reset_minutes');

        return is_numeric($minutes) ? max(1, (int) $minutes) : 60;
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
