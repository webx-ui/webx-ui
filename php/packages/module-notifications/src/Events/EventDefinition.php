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
 *         sample: [
 *             'email' => fn (string $locale): ?array => SubmissionTemplates::email($locale),
 *             '*' => ['subject' => 'New submission: {{form}}', 'template' => "{{form}}\n{{fields}}"],
 *         ],
 *     ));
 *
 * `sample` is the default template of the event — by provider key, `*` for any: what a new channel
 * starts with and what "Insert the default template" puts back. It is a starting point, never a
 * template somebody edits (decision 1). A sample is one text for every language, or a function of
 * the language that answers null for a language it has no words in. `url` turns the values of a
 * delivery into the address of what it was about, for the log.
 */
final readonly class EventDefinition
{
    /**
     * @param  list<Variable>  $variables
     * @param  array<string, array{subject?: string|null, template: string}|(Closure(string): (array{subject?: string|null, template: string}|null))>  $sample
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

    /** Whether a channel of this provider has a default template to start with. */
    public function hasSample(string $provider): bool
    {
        return isset($this->sample[$provider]) || isset($this->sample['*']);
    }

    /**
     * The default template for a provider in one language, or null when the event has none for
     * the provider or no words in that language.
     *
     * @return array{subject: string|null, template: string}|null
     */
    public function sampleFor(string $provider, string $locale = 'en'): ?array
    {
        $sample = $this->sample[$provider] ?? $this->sample['*'] ?? null;

        if ($sample instanceof Closure) {
            $sample = $sample($locale);
        }

        if (! is_array($sample) || ! is_string($sample['template'] ?? null)) {
            return null;
        }

        $subject = $sample['subject'] ?? null;

        return ['subject' => is_string($subject) ? $subject : null, 'template' => $sample['template']];
    }

    /**
     * The default template for a provider in the languages of the site, the way a channel keeps
     * it: a language the event has no words in is left out — a channel sends in the main language
     * then — and the main language always has one, the fallback language's or English if need be.
     * A sample that is one text for every language is the main language's alone, as it always was.
     *
     * @param  list<string>  $locales
     * @return array{subject: array<string, string>|null, template: array<string, string>}|null
     */
    public function samplesFor(string $provider, array $locales, string $main, string $fallback = 'en'): ?array
    {
        if (! $this->hasSample($provider)) {
            return null;
        }

        $sample = $this->sample[$provider] ?? $this->sample['*'];
        $subjects = [];
        $templates = [];

        foreach ($sample instanceof Closure ? array_values(array_unique([$main, ...$locales])) : [$main] as $locale) {
            $one = $this->sampleFor($provider, $locale);

            if ($one === null && $locale === $main) {
                $one = $this->sampleFor($provider, $fallback) ?? $this->sampleFor($provider, 'en');
            }

            if ($one === null) {
                continue;
            }

            $templates[$locale] = $one['template'];

            if ($one['subject'] !== null) {
                $subjects[$locale] = $one['subject'];
            }
        }

        if ($templates === []) {
            return null;
        }

        return ['subject' => $subjects === [] ? null : $subjects, 'template' => $templates];
    }

    /**
     * @param  array<string, string|list<array<string, string>>>  $variables
     */
    public function urlFor(array $variables): ?string
    {
        return $this->url === null ? null : ($this->url)($variables);
    }

    /**
     * The event as the panel and an agent read it; `sample` by provider key, each in the languages
     * given ({@see samplesFor()}).
     *
     * @param  list<string>  $locales
     * @return array<string, mixed>
     */
    public function toArray(array $locales = [], ?string $main = null, string $fallback = 'en'): array
    {
        $main ??= $locales[0] ?? $fallback;
        $samples = [];

        foreach (array_keys($this->sample) as $provider) {
            $samples[$provider] = $this->samplesFor($provider, $locales, $main, $fallback);
        }

        return [
            'key' => $this->key,
            'label' => (string) __($this->label),
            'module' => $this->module,
            'variables' => array_map(static fn (Variable $variable): array => $variable->toArray(), $this->variables),
            'sample' => array_filter($samples, static fn (?array $one): bool => $one !== null),
        ];
    }
}
