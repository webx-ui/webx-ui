<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Resources;

use WebxUi\Auth\Models\CmsUser;

/**
 * An administrator as a name beside something else — an assignee, the author of a log line.
 *
 * Three fields and no resource class, because it is never the subject of a response: a whole
 * account travels from `module-auth`, and repeating that shape here would be a second place
 * for it to be wrong.
 */
final class AdminBrief
{
    /**
     * @return array{id: int, name: string, email: string}|null
     */
    public static function of(?CmsUser $user): ?array
    {
        return $user === null ? null : [
            'id' => (int) $user->getKey(),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
        ];
    }
}
