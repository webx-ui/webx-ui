<?php

declare(strict_types=1);

namespace WebxUi\Events\Panel;

use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Events\Demo\EventsDemo;
use WebxUi\Events\Mcp\EventsResources;
use WebxUi\Events\Mcp\EventTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * Where events are written and published (§4.9): `events.view` opens the list, `events.manage`
 * writes.
 *
 * To an agent it is the same section by other doors (§4.11): eight tools behind `events:read` and
 * `events:write`, and `events://catalog` to read first. The demo seeds the categories with the
 * events, because an event in two categories is part of what it shows.
 */
final class EventsModule extends EventsGroup implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public const ID = 'events';

    public function __construct(
        private readonly EventTools $tools,
        private readonly EventsResources $resources,
        private readonly EventsDemo $demo,
    ) {}

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

    /**
     * Worked out, not written down: the library always, the services only where they are
     * installed (§4.12).
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return $this->demo->requires();
    }

    public function seed(DemoLedger $ledger): void
    {
        $this->demo->seed($ledger);
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return $this->tools->all();
    }

    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return $this->resources->all();
    }
}
