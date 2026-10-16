<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Events;

use Closure;
use InvalidArgumentException;

/**
 * Something a consumer notifies about (§5): `inbox.submission`, with the values its templates
 * may use and the addressees among them.
 *
 * Declared by the consumer, in its own provider and under `class_exists(NotificationEvents::class)`,
 * so the consumer does not require this module:
 *
 *     $events->register(new EventDefinition(
 *         key: 'inbox.submission',
 *         label: 'webx-inbox::notifications.event',
 *         module: 'inbox',
 *         variables: [new Variable('form', 'webx-inbox::notifications.vars.form', example: 'Contact us'), …],
 *         sample: ['*' => ['subject' => 'New submission: {{form}}', 'template' => "{{form}}\n{{fields}}"]],
 *     ));
 *
 * `sample` is what a new channel of the event starts with — by provider key, `*` for any; it is a
 * starting point, never a template somebody edits (decision 1). `url` turns the values of a
 * delivery into the address of what it was about, for the log.
 */
final readonly class EventDefinition
{
    /**
     * @param  list<Variable>  $variables
     * @param  array<string, array{subject?: string, template: string}>  $sample
     * @param  (Closure(array<string, string|list<array<string, string>>>): ?string)|null  $url
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $module,
        public array $variables = [],
        public array $sample = [],
        public ?Closure $url = null,
    ) {
        if (preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', $key) !== 1 || strlen($key) > 64) {
            throw new InvalidArgumentException("[{$key}] is not an event key: <module>.<what>, lowercase letters, digits, _ and dots, 64 at most.");
        }
    }

    /** The variable a template name stands for, a family included. */
    public function variable(string $name): ?Variable
    {
        foreach ($this->variables as $variable) {
            if ($variable->matches($name)) {
                return $variable;
            }
        }

        return null;
    }

    /**
     * Whether a name a template uses, inside these sections (innermost last), is the event's:
     * a field of an enclosing list first, then a variable of the event — how Mustache looks it up.
     *
     * @param  list<string>  $sections
     */
    public function knows(string $name, array $sections = []): bool
    {
        foreach (array_reverse($sections) as $section) {
            $list = $this->variable($section);

            if ($list !== null && $list->isList() && array_key_exists($name, $list->fields)) {
                return true;
            }
        }

        return $this->variable($name) !== null;
    }

    /**
     * The variables that are addressees of this kind of address.
     *
     * @return list<Variable>
     */
    public function addressees(?string $address = null): array
    {
        return array_values(array_filter(
            $this->variables,
            static fn (Variable $variable): bool => $variable->address !== null && ($address === null || $variable->address === $address),
        ));
    }

    /**
     * The example values, for a test message and the editor's preview.
     *
     * @return array<string, string|list<array<string, string>>>
     */
    public function examples(): array
    {
        $examples = [];

        foreach ($this->variables as $variable) {
            if (! $variable->isFamily()) {
                $examples[$variable->name] = $variable->example;
            }
        }

        return $examples;
    }

    /**
     * What a new channel of this provider starts with.
     *
     * @return array{subject: string|null, template: string}|null
     */
    public function sampleFor(string $provider): ?array
    {
        $sample = $this->sample[$provider] ?? $this->sample['*'] ?? null;

        if ($sample === null) {
            return null;
        }

        return ['subject' => $sample['subject'] ?? null, 'template' => $sample['template']];
    }

    /**
     * @param  array<string, string|list<array<string, string>>>  $variables
     */
    public function urlFor(array $variables): ?string
    {
        return $this->url === null ? null : ($this->url)($variables);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => (string) __($this->label),
            'module' => $this->module,
            'variables' => array_map(static fn (Variable $variable): array => $variable->toArray(), $this->variables),
            'sample' => $this->sample,
        ];
    }
}
