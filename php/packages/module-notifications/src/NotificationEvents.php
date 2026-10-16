<?php

declare(strict_types=1);

namespace WebxUi\Notifications;

use WebxUi\Notifications\Events\EventDefinition;

/**
 * The events consumers notify about (§5). An event with no channel is normal: the consumer calls
 * `send()` with an empty list and nothing happens.
 */
final class NotificationEvents
{
    /** @var array<string, EventDefinition> */
    private array $events = [];

    public function register(EventDefinition $event): void
    {
        $this->events[$event->key] = $event;
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
