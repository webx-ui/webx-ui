<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Channels;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Mail\MailRoutes;
use WebxUi\Localization\Locales;
use WebxUi\Notifications\Contracts\HasMailRoute;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Providers;

/**
 * A channel as the panel reads and writes it (§10.3, §10.5) — and, next, the agent's tools.
 *
 * Nothing here ever answers with a secret: a channel says which of its secrets are set
 * (`credentials_set`), and the secret fields of its form come back empty (decision 3).
 */
final class ChannelForm
{
    public function __construct(
        private readonly ChannelValues $values,
        private readonly Providers $providers,
        private readonly NotificationEvents $events,
        private readonly Locales $locales,
        private readonly ConnectionInterface $db,
        private readonly Cache $cache,
        private readonly MailRoutes $routes,
    ) {}

    /**
     * A channel, the values of its form, and what to look at although it was saved.
     *
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    public function describe(Channel $channel, array $warnings = []): array
    {
        return [
            'channel' => $this->row($channel),
            'values' => $this->valuesOf($channel),
            'warnings' => $warnings,
            'mail' => $this->mail($channel),
        ];
    }

    /**
     * Where a channel's letters go, as the application sees it now: the mailer and its server,
     * a trap or a config not in effect yet, and how long the queue keeps a letter. Null for a
     * channel that sends no mail.
     *
     * @return array{route: array<string, mixed>, warnings: list<array{code: string, message: string}>, queue: array{driver: string, sync: bool, hint: string|null}}|null
     */
    public function mail(Channel $channel): ?array
    {
        $provider = $this->providers->find($channel->provider);

        if (! $provider instanceof HasMailRoute) {
            return null;
        }

        $route = $provider->route($channel->options());

        return [
            'route' => $route->toArray(),
            'warnings' => $this->routes->warnings($route),
            'queue' => $this->routes->queue(),
        ];
    }

    /**
     * The form's values: open settings flat by name, secret ones always empty.
     *
     * @return array<string, mixed>
     */
    public function valuesOf(Channel $channel): array
    {
        $values = [
            'name' => $channel->name,
            'provider' => $channel->provider,
            'event' => $channel->event,
            'enabled' => $channel->enabled,
            'recipients' => $channel->recipientList(),
            'subject' => $channel->getTranslations('subject'),
            'template' => $channel->getTranslations('template'),
        ];

        foreach ($this->providers->find($channel->provider)?->fields() ?? [] as $field) {
            $values[$field->name] = $field->secret ? '' : ($channel->options()[$field->name] ?? $field->default);
        }

        return $values;
    }

    /**
     * One channel as a row of the list and the head of its form.
     *
     * @return array<string, mixed>
     */
    public function row(Channel $channel, ?Delivery $last = null): array
    {
        $provider = $this->providers->find($channel->provider);
        $event = $channel->event === null ? null : $this->events->find($channel->event);
        $last ??= $channel->exists
            ? Delivery::query()->where('channel_id', $channel->id)->where('is_test', false)->whereIn('status', [Delivery::SENT, Delivery::FAILED])->latest('id')->first()
            : null;

        return [
            'id' => $channel->exists ? (int) $channel->id : null,
            'name' => $channel->name,
            'provider' => $channel->provider,
            'provider_label' => $provider === null ? $channel->provider : (string) __($provider->label()),
            'provider_declared' => $provider !== null,
            'icon' => $provider?->icon(),
            'event' => $channel->event,
            'event_label' => $event === null ? null : (string) __($event->label),
            'event_declared' => $channel->event === null || $event !== null,
            'recipients' => count($channel->recipientList()),
            'enabled' => $channel->enabled,
            'credentials_set' => array_fill_keys($channel->credentialsSet(), true),
            'credentials_readable' => $channel->readable(),
            'bot' => $channel->exists ? $this->cache->get(self::botKey($channel)) : null,
            'copied_from' => $channel->copied_from,
            'last_delivery' => $last === null ? null : [
                'status' => $last->status,
                'at' => ($last->sent_at ?? $last->created_at)?->toAtomString(),
                'error' => $last->error,
            ],
            'updated_at' => $channel->updated_at?->toAtomString(),
            'deleted_at' => $channel->deleted_at?->toAtomString(),
        ];
    }

    /**
     * Check what came in and write it. A new channel of an event starts with the event's sample
     * when no template came.
     *
     * @param  array<string, mixed>  $input
     * @return array{channel: Channel, warnings: list<string>}
     *
     * @throws ValidationException
     */
    public function save(Channel $channel, array $input): array
    {
        $input = $this->withSample($channel, $input);
        $checked = $this->values->check($input, $channel);

        $channel = $this->db->transaction(static function () use ($channel, $checked): Channel {
            $channel->fill($checked['values'])->save();

            return $channel->refresh();
        });

        return ['channel' => $channel, 'warnings' => $checked['warnings']];
    }

    /**
     * The form's values over the saved channel, not written: what a test, a preview or a check
     * of a form not saved yet runs on. Secrets typed in the form are used for this one call.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function unsaved(array $input, ?Channel $channel = null): Channel
    {
        $base = $channel ?? new Channel;

        // A form not saved yet may have no name: a test or a search does not need one.
        if ($channel === null && trim(is_string($input['name'] ?? null) ? $input['name'] : '') === '') {
            $input['name'] = (string) __('webx-notifications::module.unsaved');
        }
        $input = $this->withSample($base, $input);
        $checked = $this->values->check(['enabled' => false] + $input, $base);
        $copy = $channel === null ? new Channel : (clone $channel);

        return $copy->fill(['enabled' => false, ...$checked['values']]);
    }

    /**
     * A copy (§3.4): the same settings, its secrets carried over encrypted on the server — never
     * through the panel or the agent — switched off, and saying where it came from.
     */
    public function copy(Channel $channel, ?string $name = null): Channel
    {
        return $this->db->transaction(function () use ($channel, $name): Channel {
            $copy = $channel->replicate(['deleted_at', 'created_at', 'updated_at']);
            $copy->name = mb_substr($name !== null && trim($name) !== '' ? trim($name) : (string) __('webx-notifications::module.copy-name', ['name' => $channel->name]), 0, ChannelValues::NAME_LENGTH);
            $copy->enabled = false;
            $copy->copied_from = (int) $channel->id;

            // `replicate()` copies the raw attributes, so the secrets travel as ciphertext: never
            // decrypted on the way.
            $copy->save();

            return $copy->refresh();
        });
    }

    /** Where the bot's name found by a check waits for the form (§6.1). */
    public static function botKey(Channel $channel): string
    {
        return 'webx-notifications:bot:'.$channel->id.':'.substr(hash('sha256', (string) $channel->getRawOriginal('credentials')), 0, 16);
    }

    /**
     * The event as the panel and an agent read it, its default templates in the site's languages.
     *
     * @return array<string, mixed>
     */
    public function event(EventDefinition $event): array
    {
        return $event->toArray($this->locales->codes(), $this->locales->defaultCode(), $this->locales->fallback());
    }

    /**
     * The event's default template for a provider, a text per language of the site; null when the
     * event has none for it.
     *
     * @return array{subject: array<string, string>|null, template: array<string, string>}|null
     */
    public function sample(EventDefinition $event, string $provider): ?array
    {
        return $event->samplesFor($provider, $this->locales->codes(), $this->locales->defaultCode(), $this->locales->fallback());
    }

    /**
     * A new channel of an event without a template starts with the event's default one; any
     * channel asked for it (`default_template: true`) gets it in place of its subject and template.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function withSample(Channel $channel, array $input): array
    {
        $reset = ($input['default_template'] ?? false) === true;
        unset($input['default_template']);

        if (! $reset && ($channel->exists || array_key_exists('template', $input))) {
            return $input;
        }

        $key = array_key_exists('event', $input) ? $input['event'] : $channel->event;
        $provider = $input['provider'] ?? $channel->provider;
        $event = is_string($key) ? $this->events->find($key) : null;
        $sample = $event === null || ! is_string($provider) ? null : $this->sample($event, $provider);

        if ($sample === null) {
            if ($reset) {
                throw ValidationException::withMessages(['template' => (string) __('webx-notifications::errors.no-sample', ['event' => is_string($key) ? $key : ''])]);
            }

            return $input;
        }

        $input['template'] = $sample['template'];

        if ($sample['subject'] !== null && ($reset || ! array_key_exists('subject', $input))) {
            $input['subject'] = $sample['subject'];
        }

        return $input;
    }
}
