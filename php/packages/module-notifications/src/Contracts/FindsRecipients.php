<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Contracts;

use WebxUi\Notifications\Sending\Credentials;
use WebxUi\Notifications\Sending\DeliveryFailed;
use WebxUi\Notifications\Sending\FoundRecipient;

/**
 * A provider that can say whom its credentials could write to — Telegram's "Find chats" (§6.3).
 * The panel and the agent offer the search only to providers that implement it.
 */
interface FindsRecipients
{
    /**
     * Read, never write: nothing is sent and nothing is logged.
     *
     * @return list<FoundRecipient>
     *
     * @throws DeliveryFailed
     */
    public function find(Credentials $credentials): array;
}
