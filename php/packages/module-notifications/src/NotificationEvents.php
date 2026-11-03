<?php

declare(strict_types=1);

namespace WebxUi\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Events\Variable;
use WebxUi\Notifications\Relations\ChannelTarget;

/**
 * The events consumers notify about (§5). An event with no channel is normal: the consumer calls
 * `send()` with an empty list and nothing happens.
 *
 * Each event brings the target its consumer chooses channels from (`notify-<event>`, §15.2), so a
 * consumer declares the event and nothing else.
 *
 * Another module may add variables to an event it does not own ({@see extend()}): the payments add
 * the pay link to the order's events. The definitions this registry hands out carry those
 * variables as if the owner had declared them — the channel's editor, the examples, the check of a
 * template all read one list — and `send()` asks the extension for its values from the subject.
 */
final class NotificationEvents
{
    /** @var array<string, EventDefinition> as their owners declared them */
    private array $events = [];

    /** @var list<array{events: list<string>, variables: list<Variable>, values: callable(?Model, string): array<string, mixed>}> */
    private array $extensions = [];

    /** @var array<string, EventDefinition> the definitions with the extensions' variables, built on first ask */
    private array $merged = [];

    public function __construct(
        private readonly ?RelationTargets $targets = null,
        private readonly ?Providers $providers = null,
    ) {}

    /**
     * @throws InvalidArgumentException the event declares a name an extension of it declared first
     */
    public function register(EventDefinition $event): void
    {
        foreach ($this->extensionsOf($event->key) as $extension) {
            self::refuseClash($event, $extension['variables']);
        }

        $this->events[$event->key] = $event;
        $this->merged = [];

        if ($this->targets !== null && $this->providers !== null) {
            $this->targets->register(new ChannelTarget($event->key, $this->providers));
        }
    }

    /**
     * Another module adds variables to events it does not own: payments adds a pay link to the
     * order's events. The values are computed at send() from the subject, before the snapshot.
     *
     * An event registered later is extended too: the providers boot in no particular order.
     *
     * @param  list<string>  $events  event keys or a prefix with '*': 'orders.*'
     * @param  list<Variable>  $variables
     * @param  callable(?Model $subject, string $event): array<string, mixed>  $values
     *
     * @throws InvalidArgumentException a name the owner of a matching event declared already
     */
    public function extend(array $events, array $variables, callable $values): void
    {
        $extension = ['events' => array_values($events), 'variables' => array_values($variables), 'values' => $values];

        foreach ($this->events as $key => $event) {
            if (self::matches($extension['events'], $key)) {
                self::refuseClash($event, $extension['variables']);
            }
        }

        $this->extensions[] = $extension;
        $this->merged = [];
    }

    public function find(string $key): ?EventDefinition
    {
        if (! isset($this->events[$key])) {
            return null;
        }

        return $this->merged[$key] ??= $this->merge($this->events[$key]);
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
        $all = [];

        foreach (array_keys($this->events) as $key) {
            $all[$key] = $this->find($key);
        }

        return array_filter($all);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->events);
    }

    /**
     * The values the extensions of an event give for this subject. An extension that fails gives
     * its variables empty and a warning in the log: a notification never fails because of a
     * module that only added to it.
     *
     * @return array<string, mixed>
     */
    public function extraValues(string $event, ?Model $subject): array
    {
        $values = [];

        foreach ($this->extensionsOf($event) as $extension) {
            $names = array_map(static fn (Variable $variable): string => $variable->name, $extension['variables']);

            try {
                $given = ($extension['values'])($subject, $event);
                $given = is_array($given) ? $given : [];
            } catch (Throwable $exception) {
                Log::warning("Notifications: the values an extension adds to [{$event}] could not be read. ".$exception->getMessage());
                $given = [];
            }

            foreach ($names as $name) {
                $values[$name] = $given[$name] ?? '';
            }
        }

        return $values;
    }

    private function merge(EventDefinition $event): EventDefinition
    {
        $added = [];

        foreach ($this->extensionsOf($event->key) as $extension) {
            $added = [...$added, ...$extension['variables']];
        }

        if ($added === []) {
            return $event;
        }

        return new EventDefinition(
            key: $event->key,
            label: $event->label,
            module: $event->module,
            variables: [...$event->variables, ...$added],
            sample: $event->sample,
            url: $event->url,
        );
    }

    /**
     * @return list<array{events: list<string>, variables: list<Variable>, values: callable(?Model, string): array<string, mixed>}>
     */
    private function extensionsOf(string $key): array
    {
        return array_values(array_filter($this->extensions, static fn (array $extension): bool => self::matches($extension['events'], $key)));
    }

    /** @param  list<string>  $patterns */
    private static function matches(array $patterns, string $key): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $key || (str_ends_with($pattern, '*') && str_starts_with($key, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<Variable>  $variables */
    private static function refuseClash(EventDefinition $event, array $variables): void
    {
        foreach ($variables as $variable) {
            if ($event->variable($variable->name) !== null) {
                throw new InvalidArgumentException("[{$event->key}] declares [{$variable->name}] itself: an extension may not replace a variable of the event's owner.");
            }
        }
    }
}
