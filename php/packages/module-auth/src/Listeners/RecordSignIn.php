<?php

declare(strict_types=1);

namespace WebxUi\Auth\Listeners;

use WebxUi\Auth\Events\AdminLoggedIn;
use WebxUi\Auth\Events\AdminLoginFailed;
use WebxUi\Auth\Models\LoginRecord;

/**
 * Writes the sign-in trail. Failures matter more than successes here: a burst of them against
 * one address, or one address against many, is the shape of an attack.
 */
final class RecordSignIn
{
    public function handleSuccess(AdminLoggedIn $event): void
    {
        LoginRecord::query()->create([
            'cms_user_id' => $event->user->id,
            'email' => $event->user->email,
            'successful' => true,
            'ip' => $event->ip,
            'user_agent' => $this->trim($event->userAgent),
        ]);
    }

    public function handleFailure(AdminLoginFailed $event): void
    {
        LoginRecord::query()->create([
            'cms_user_id' => $event->userId,
            'email' => $event->email,
            'successful' => false,
            'ip' => $event->ip,
            'user_agent' => $this->trim($event->userAgent),
        ]);
    }

    private function trim(?string $userAgent): ?string
    {
        return $userAgent === null ? null : mb_substr($userAgent, 0, 512);
    }
}
