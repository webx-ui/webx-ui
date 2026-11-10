<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Events;

use Closure;
use InvalidArgumentException;
use WebxUi\Templates\Variables;

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
 *
 * `addressed` is the default template of a channel that writes to an addressee (§5.1): by the
 * name of an addressee variable, then by provider key as `sample` is. A channel whose recipients
 * name that variable starts with it; any other channel — and an addressee with no words of its
 * own for the provider — with `sample`:
 *
 *     sample: ['email' => fn (string $locale) => ShopLetters::placed($locale)],
 *     addressed: ['customer_email' => ['email' => fn (string $locale) => BuyerLetters::placed($locale)]],
 */
final readonly class EventDefinition
{
    /**
     * @param  list<Variable>  $variables
     * @param  array<string, array{subject?: string|null, template: string}|(Closure(string): (array{subject?: string|null, template: string}|null))>  $sample
     * @param  (Closure(array<string, string|list<array<string, string>>>): ?string)|null  $url
     * @param  array<string, array<string, array{subject?: string|null, template: string}|(Closure(string): (array{subject?: string|null, template: string}|null))>>  $addressed
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $module,
        public array $variables = [],
        public array $sample = [],
        public ?Closure $url = null,
        public array $addressed = [],
    ) {
        if (preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', $key) !== 1 || strlen($key) > 64) {
            throw new InvalidArgumentException("[{$key}] is not an event key: <module>.<what>, lowercase letters, digits, _ and dots, 64 at most.");
        }
    }

    /** The variable a template name stands for, a family included. */
    public function variable(string $name): ?Variable
    {
        return (new Variables($this->variables))->variable($name);
    }

    /**
     * Whether a name a template uses, inside these sections (innermost last), is the event's:
     * a field of an enclosing list first, then a variable of the event — how Mustache looks it up.
     *
     * @param  list<string>  $sections
     */
    public function knows(string $name, array $sections = []): bool
    {
        return (new Variables($this->variables))->knows($name, $sections);
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
        return (new Variables($this->variables))->examples();
    }

    /**
     * Whom a channel with these recipients writes to, as far as the default template goes: the
     * first addressee variable among them that the event has a template of its own for, with this
     * provider — null for a channel to fixed addresses, which takes `sample`.
     *
     * @param  mixed  $recipients  the channel's, as it keeps or receives them
     */
    public function addresseeOf(mixed $recipients, string $provider): ?string
    {
        if (! is_array($recipients)) {
            return null;
        }

        foreach ($recipients as $recipient) {
            $name = is_array($recipient) ? ($recipient['variable'] ?? null) : null;

            if (is_string($name) && (isset($this->addressed[$name][$provider]) || isset($this->addressed[$name]['*']))) {
                return $name;
            }
        }

        return null;
    }

    /** Whether a channel of this provider (to this addressee) has a default template to start with. */
    public function hasSample(string $provider, ?string $addressee = null): bool
    {
        return $this->pick($provider, $addressee) !== null;
    }

    /**
     * The default template for a provider in one language, or null when the event has none for
     * the provider or no words in that language. With an addressee the event has a template of
     * its own for, that one ({@see addresseeOf()}).
     *
     * @return array{subject: string|null, template: string}|null
     */
    public function sampleFor(string $provider, string $locale = 'en', ?string $addressee = null): ?array
    {
        $sample = $this->pick($provider, $addressee);

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
    public function samplesFor(string $provider, array $locales, string $main, string $fallback = 'en', ?string $addressee = null): ?array
    {
        $sample = $this->pick($provider, $addressee);

        if ($sample === null) {
            return null;
        }

        $subjects = [];
        $templates = [];

        foreach ($sample instanceof Closure ? array_values(array_unique([$main, ...$locales])) : [$main] as $locale) {
            $one = $this->sampleFor($provider, $locale, $addressee);

            if ($one === null && $locale === $main) {
                $one = $this->sampleFor($provider, $fallback, $addressee) ?? $this->sampleFor($provider, 'en', $addressee);
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
            // An addressee without a word of its own anywhere: the event's template, as for anyone.
            return $addressee === null ? null : $this->samplesFor($provider, $locales, $main, $fallback);
        }

        return ['subject' => $subjects === [] ? null : $subjects, 'template' => $templates];
    }

    /**
     * The sample of a provider: the addressee's own when the event has one for it, else the
     * event's — by the provider's key, then `*`.
     *
     * @return array{subject?: string|null, template: string}|(Closure(string): (array{subject?: string|null, template: string}|null))|null
     */
    private function pick(string $provider, ?string $addressee): array|Closure|null
    {
        $own = $addressee === null ? null : ($this->addressed[$addressee][$provider] ?? $this->addressed[$addressee]['*'] ?? null);

        return $own ?? $this->sample[$provider] ?? $this->sample['*'] ?? null;
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
     * given ({@see samplesFor()}); `addressed` the same, by addressee variable first.
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

        $addressed = [];

        foreach ($this->addressed as $addressee => $byProvider) {
            foreach (array_keys($byProvider) as $provider) {
                $one = $this->samplesFor($provider, $locales, $main, $fallback, $addressee);

                if ($one !== null) {
                    $addressed[$addressee][$provider] = $one;
                }
            }
        }

        return [
            'key' => $this->key,
            'label' => (string) __($this->label),
            'module' => $this->module,
            'variables' => array_map(static fn (Variable $variable): array => $variable->toArray(), $this->variables),
            'sample' => array_filter($samples, static fn (?array $one): bool => $one !== null),
            'addressed' => $addressed,
        ];
    }
}
