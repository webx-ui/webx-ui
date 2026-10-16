<?php

declare(strict_types=1);

namespace WebxUi\Notifications;

use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Relations\ChannelTarget;

/**
 * The events consumers notify about (§5). An event with no channel is normal: the consumer calls
 * `send()` with an empty list and nothing happens.
 *
 * Each event brings the target its consumer chooses channels from (`notify-<event>`, §15.2), so a
 * consumer declares the event and nothing else.
 */
final class NotificationEvents
{
    /** @var array<string, EventDefinition> */
    private array $events = [];

    public function __construct(
        private readonly ?RelationTargets $targets = null,
        private readonly ?Providers $providers = null,
    ) {}

    public function register(EventDefinition $event): void
    {
        $this->events[$event->key] = $event;

        if ($this->targets !== null && $this->providers !== null) {
            $this->targets->register(new ChannelTarget($event->key, $this->providers));
        }
    }

    public function find(string $key): ?EventDefinition
    {
        return $this->events[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->events[$key]);
    }

    /**
     * @return array<string, EventDefinition>
     */
    public function all(): array
    {
        return $this->events;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->events);
    }
}
