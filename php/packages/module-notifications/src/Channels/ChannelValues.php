<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Channels;

use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Support\Field;
use WebxUi\Localization\Locales;
use WebxUi\Notifications\Contracts\Provider;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Providers;
use WebxUi\Notifications\Refusal;
use WebxUi\Notifications\Templates\Template;
use WebxUi\Notifications\Templates\TemplateError;

/**
 * What a channel may hold (§3.3), checked the same way for every door that writes one — the
 * panel's form now, the agent's tools next.
 *
 * Input: `name`, `provider`, `event`, `recipients`, `subject` and `template` (a map of languages,
 * or one text for the site's main language), `enabled`, the provider's settings either as
 * `credentials` and `options` objects or flat by their names, and `forget` — secrets to wipe.
 * A field that did not come is left as it is. A secret that came empty is kept: the form never
 * has it to send back (decision 3).
 *
 * A refusal is a `ValidationException` under the field's name. A template Mustache cannot read, or
 * one with a partial or a change of delimiters, is refused (§7); a name no event has is a warning on
 * a channel for any event, a refusal on a channel for one.
 */
final class ChannelValues
{
    public const NAME_LENGTH = 160;

    public const TEMPLATE_LENGTH = 16000;

    public const SUBJECT_LENGTH = 255;

    public function __construct(
        private readonly Providers $providers,
        private readonly NotificationEvents $events,
        private readonly Locales $locales,
    ) {}

    /**
     * The attributes to write, checked; and what to look at although it was accepted.
     *
     * @param  array<string, mixed>  $input
     * @return array{values: array<string, mixed>, warnings: list<string>}
     *
     * @throws ValidationException
     */
    public function check(array $input, Channel $channel): array
    {
        $creating = ! $channel->exists;
        $errors = [];
        $values = [];

        // The kind is chosen once (decision 7): a Telegram channel never turns into a letter.
        $key = $creating ? $input['provider'] ?? null : $channel->provider;

        if (! $creating && array_key_exists('provider', $input) && $input['provider'] !== $channel->provider) {
            $errors['provider'] = $this->say('provider-fixed');
        }

        $provider = is_string($key) ? $this->providers->find($key) : null;

        if ($provider === null) {
            throw ValidationException::withMessages($errors + ['provider' => $this->say('provider', [
                'provider' => is_string($key) ? $key : '',
                'providers' => implode(', ', $this->providers->keys()),
            ])]);
        }

        if ($creating) {
            $values['provider'] = $provider->key();
        }

        foreach (['name' => fn (mixed $value): string => $this->name($value), 'event' => fn (mixed $value): ?string => $this->event($value, $channel)] as $field => $check) {
            if (! array_key_exists($field, $input)) {
                if ($creating && $field === 'name') {
                    $errors['name'] = $this->say('required', ['field' => 'name']);
                }

                continue;
            }

            try {
                $values[$field] = $check($input[$field]);
            } catch (Refusal $refusal) {
                $errors[$field] = $refusal->getMessage();
            }
        }

        $eventKey = array_key_exists('event', $values) ? $values['event'] : $channel->event;
        $event = $eventKey === null ? null : $this->events->find($eventKey);

        try {
            [$values['options'], $values['credentials']] = $this->settings($provider, $input, $channel);
        } catch (Refusal $refusal) {
            $errors['options'] = $refusal->getMessage();
        }

        if (array_key_exists('recipients', $input)) {
            try {
                $values['recipients'] = $this->recipients($provider, $input['recipients'], $event);
            } catch (Refusal $refusal) {
                $errors['recipients'] = $refusal->getMessage();
            }
        }

        $warnings = [];

        foreach (['template', 'subject'] as $field) {
            if ($field === 'subject' && ! $provider->hasSubject()) {
                continue;
            }

            if (! array_key_exists($field, $input)) {
                continue;
            }

            try {
                $values[$field] = $this->texts($field, $input[$field], $provider, $event, $warnings);
            } catch (Refusal $refusal) {
                $errors[$field] = $refusal->getMessage();
            }
        }

        if (array_key_exists('enabled', $input)) {
            $enabled = is_bool($input['enabled']) ? $input['enabled'] : filter_var($input['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($enabled === null) {
                $errors['enabled'] = $this->say('boolean', ['field' => 'enabled']);
            } else {
                $values['enabled'] = $enabled;
            }
        }

        if ($errors === []) {
            $refusal = $this->incomplete($provider, $values, $channel, $event);

            if ($refusal !== null) {
                $errors['enabled'] = $refusal;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['values' => $values, 'warnings' => array_values(array_unique($warnings))];
    }

    /**
     * The settings of the provider, split by secrecy and merged over what the channel has.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     *
     * @throws Refusal
     */
    private function settings(Provider $provider, array $input, Channel $channel): array
    {
        $options = $channel->options();
        $secrets = $channel->secrets()->values();
        $given = [
            ...(is_array($input['options'] ?? null) ? $input['options'] : []),
            ...(is_array($input['credentials'] ?? null) ? $input['credentials'] : []),
        ];

        foreach ($provider->fields() as $field) {
            if (array_key_exists($field->name, $input)) {
                $given[$field->name] = $input[$field->name];
            }
        }

        $forget = is_array($input['forget'] ?? null) ? array_filter($input['forget'], is_string(...)) : [];

        foreach ($provider->fields() as $field) {
            if ($field->secret) {
                $value = $given[$field->name] ?? null;

                if (in_array($field->name, $forget, true)) {
                    unset($secrets[$field->name]);
                } elseif (is_string($value) && trim($value) !== '') {
                    $secrets[$field->name] = trim($value);
                }

                continue;
            }

            if (array_key_exists($field->name, $given)) {
                $options[$field->name] = $this->option($field, $given[$field->name]);
            } elseif (! array_key_exists($field->name, $options) && $field->default !== null) {
                $options[$field->name] = $field->default;
            }
        }

        $known = array_map(static fn (Field $field): string => $field->name, $provider->fields());

        return [array_intersect_key($options, array_flip($known)), $secrets];
    }

    /** @throws Refusal */
    private function option(Field $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $field->kind === Field::CHOICE ? $field->default : null;
        }

        return match ($field->kind) {
            Field::NUMBER => is_numeric($value) && (int) $value > 0 && (int) $value < 65536
                ? (int) $value
                : throw new Refusal($this->say('option', ['field' => $field->name])),
            Field::CHOICE => is_string($value) && in_array($value, $field->choices, true)
                ? $value
                : throw new Refusal($this->say('choice', ['field' => $field->name, 'choices' => implode(', ', $field->choices)])),
            Field::EMAIL => is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false
                ? trim($value)
                : throw new Refusal($this->say('email.address', ['address' => is_scalar($value) ? (string) $value : ''])),
            default => is_scalar($value) && mb_strlen(trim((string) $value)) <= $field->max
                ? trim((string) $value)
                : throw new Refusal($this->say('option', ['field' => $field->name])),
        };
    }

    /**
     * The fixed recipients by the provider's rules; an event's addressee only when the channel is
     * for that event and the addressee is of the provider's kind.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Refusal
     */
    private function recipients(Provider $provider, mixed $value, ?EventDefinition $event): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new Refusal($this->say('recipients-shape'));
        }

        foreach ($value as $recipient) {
            if (! is_array($recipient) || ! array_key_exists('variable', $recipient)) {
                continue;
            }

            $name = $recipient['variable'];
            $addressees = array_map(static fn ($variable): string => $variable->name, $event?->addressees($provider->address()) ?? []);

            if (! is_string($name) || ! in_array($name, $addressees, true)) {
                throw new Refusal($event === null
                    ? $this->say('addressee-without-event')
                    : $this->say('addressee', [
                        'variable' => is_string($name) ? $name : '',
                        'event' => $event->key,
                        'addressees' => $addressees === [] ? '—' : implode(', ', $addressees),
                    ]));
            }
        }

        return $provider->recipients(array_values(array_filter($value, static fn (mixed $one): bool => $one !== null)));
    }

    /**
     * A text in languages: one string is the site's main language, a map is every language at
     * once. Empty languages are dropped.
     *
     * @param  list<string>  $warnings
     * @return array<string, string>
     *
     * @throws Refusal
     */
    private function texts(string $field, mixed $value, Provider $provider, ?EventDefinition $event, array &$warnings): array
    {
        if ($value === null) {
            $value = [];
        }

        if (is_string($value)) {
            $value = [$this->locales->defaultCode() => $value];
        }

        if (! is_array($value)) {
            throw new Refusal($this->say('texts', ['field' => $field]));
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if ($text === null || (is_string($text) && trim($text) === '')) {
                continue;
            }

            if (! is_string($text) || ! is_string($locale)) {
                throw new Refusal($this->say('texts', ['field' => $field]));
            }

            if ($field === 'subject') {
                $text = trim((string) preg_replace('/[\r\n]+/', ' ', $text));

                if (mb_strlen($text) > self::SUBJECT_LENGTH) {
                    throw new Refusal($this->say('subject-long', ['max' => self::SUBJECT_LENGTH]));
                }
            } elseif (mb_strlen($text) > self::TEMPLATE_LENGTH) {
                throw new Refusal($this->say('template-long', ['max' => self::TEMPLATE_LENGTH]));
            }

            try {
                $usages = Template::usages($text);
            } catch (TemplateError $error) {
                throw new Refusal($error->words());
            }

            foreach ($usages as $usage) {
                $this->checkUsage($usage, $event, $warnings);
            }

            if ($field === 'template') {
                $problems = $provider->checkTemplate($text);

                if ($problems !== []) {
                    throw new Refusal($problems[0]);
                }
            }

            $texts[$locale] = $text;
        }

        return $texts;
    }

    /**
     * A switched-on channel has to be able to send (§3.3): its secrets, its recipients and a
     * template in at least one language.
     *
     * @param  array<string, mixed>  $values
     */
    private function incomplete(Provider $provider, array $values, Channel $channel, ?EventDefinition $event): ?string
    {
        $enabled = (bool) ($values['enabled'] ?? $channel->enabled);

        if (! $enabled) {
            return null;
        }

        $options = $values['options'] ?? $channel->options();
        $secrets = array_keys($values['credentials'] ?? []);
        $missing = $provider->missing($options, $secrets);

        if ($missing !== null) {
            return $this->say('cannot-enable', ['why' => $this->say($missing)]);
        }

        $recipients = $values['recipients'] ?? $channel->recipientList();

        if ($recipients === []) {
            return $this->say('cannot-enable', ['why' => $this->say('missing-recipients')]);
        }

        $template = $values['template'] ?? $channel->getTranslations('template');

        if (array_filter($template, static fn (mixed $text): bool => is_string($text) && trim($text) !== '') === []) {
            return $this->say('cannot-enable', ['why' => $this->say('missing-template')]);
        }

        if ($provider->hasSubject()) {
            $subject = $values['subject'] ?? $channel->getTranslations('subject');

            if (array_filter($subject, static fn (mixed $text): bool => is_string($text) && trim($text) !== '') === []) {
                return $this->say('cannot-enable', ['why' => $this->say('missing-subject')]);
            }
        }

        return null;
    }

    private function name(mixed $value): string
    {
        $name = is_string($value) ? trim($value) : '';

        if ($name === '') {
            throw new Refusal($this->say('required', ['field' => 'name']));
        }

        if (mb_strlen($name) > self::NAME_LENGTH) {
            throw new Refusal($this->say('name-long', ['max' => self::NAME_LENGTH]));
        }

        return $name;
    }

    /** A declared event, or none; an event that went away may stay on a channel that had it. */
    private function event(mixed $value, Channel $channel): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || (! $this->events->has($value) && $value !== $channel->event)) {
            throw new Refusal($this->say('event', [
                'event' => is_scalar($value) ? (string) $value : '',
                'events' => $this->events->keys() === [] ? '—' : implode(', ', $this->events->keys()),
            ]));
        }

        return $value;
    }

    /**
     * A name is the event's where it is used — a field inside its list, a variable outside — and
     * a list is gone over, not printed. A channel for any event only gets a warning.
     *
     * @param  array{name: string, sections: list<string>, kind: string}  $usage
     * @param  list<string>  $warnings
     *
     * @throws Refusal
     */
    private function checkUsage(array $usage, ?EventDefinition $event, array &$warnings): void
    {
        ['name' => $name, 'sections' => $sections, 'kind' => $kind] = $usage;

        if ($event === null) {
            if (! $this->declaredAnywhere($name, $sections)) {
                $warnings[] = $this->say('variable-unknown', ['variable' => $name]);
            }

            return;
        }

        if (! $event->knows($name, $sections)) {
            $names = [];

            foreach ($event->variables as $variable) {
                $names[] = $variable->isList() ? $variable->name.' ('.implode(', ', array_keys($variable->fields)).')' : $variable->name;
            }

            throw new Refusal($this->say('variable', [
                'variable' => $name,
                'event' => $event->key,
                'variables' => implode(', ', $names),
            ]));
        }

        if ($kind === Template::VALUE && $event->variable($name)?->isList() === true && ! $this->isField($event, $name, $sections)) {
            throw new Refusal($this->say('variable-list', ['variable' => $name]));
        }
    }

    /**
     * @param  list<string>  $sections
     */
    private function isField(EventDefinition $event, string $name, array $sections): bool
    {
        foreach ($sections as $section) {
            if (array_key_exists($name, $event->variable($section)->fields ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $sections
     */
    private function declaredAnywhere(string $name, array $sections): bool
    {
        foreach ($this->events->all() as $event) {
            if ($event->knows($name, $sections)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private function say(string $key, array $replace = []): string
    {
        $line = __('webx-notifications::errors.'.$key, $replace);

        return is_string($line) ? $line : $key;
    }
}
