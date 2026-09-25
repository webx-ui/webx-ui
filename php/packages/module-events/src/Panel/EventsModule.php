<?php

declare(strict_types=1);

namespace WebxUi\Events\Panel;

/**
 * Where events are written and published (§4.9): `events.view` opens the list, `events.manage`
 * writes.
 */
final class EventsModule extends EventsGroup
{
    public const ID = 'events';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-events::module.events');
    }

    public function icon(): string
    {
        return 'calendar';
    }

    public function order(): int
    {
        return 600;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['events.view', 'events.manage'];
    }
}
