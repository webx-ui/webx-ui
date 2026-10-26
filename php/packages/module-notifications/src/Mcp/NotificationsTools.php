<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Tool;
use WebxUi\Notifications\Channels\ChannelForm;
use WebxUi\Notifications\Contracts\FindsRecipients;
use WebxUi\Notifications\Contracts\Provider;
use WebxUi\Notifications\Contracts\WritesHtml;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Http\Controllers\DeliveryController;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Notifications;
use WebxUi\Notifications\Panel\NotificationsModule;
use WebxUi\Notifications\Providers;
use WebxUi\Notifications\Refusal;
use WebxUi\Notifications\Sending\DeliveryFailed;
use WebxUi\Notifications\Sending\FoundRecipient;
use WebxUi\Notifications\Sending\Recipient;
use WebxUi\Notifications\Sending\Renderer;

/**
 * What an agent can do with the channels (§12 of the notifications spec): the panel's doors by
 * other names.
 *
 * Values go through {@see ChannelForm} — the rules of `ChannelValues` on every path, a template
 * Mustache cannot read refused with the words that say what to fix. Secrets go one way: an agent
 * writes `credentials`, and no answer, a dry run's and a refusal's included, carries them back —
 * only `credentials_set`.
 *
 * Reading is `notifications.view` (the log holds what visitors typed), writing, testing, searching
 * chats and retrying are `notifications.manage`. A dry run of a save is the save, rolled back
 * ({@see Rehearsal}); a dry run of what reaches outside — a test, a search of chats, a retry —
 * says what it would do and does not call out.
 */
final class NotificationsTools
{
    /** The opening of every write's description (§12.1): the agent reads this before it decides. */
    private const WARNING = 'A channel sends messages to real people outside the site. Do not enable a channel or send a test '
        .'unless the person asked; never invent tokens, chat ids or addresses — ask for them. Use dry_run first. ';

    private const LIMIT = 50;

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $channel = ['type' => 'integer', 'description' => 'The channel id; notifications_channels_list has them all.'];
        $texts = ['type' => ['string', 'object'], 'additionalProperties' => ['type' => 'string'], 'description' => ''];
        $fields = [
            'name' => ['type' => 'string', 'description' => 'What the administrators call it: "Orders to the managers\' chat". Up to 160 characters.'],
            'event' => ['type' => ['string', 'null'], 'description' => 'The key of a declared event (notifications://events): the template is checked against its variables, and only its consumer offers the channel. Null or left out — a channel for any event.'],
            'credentials' => ['type' => 'object', 'additionalProperties' => ['type' => 'string'], 'description' => 'The secret settings of the provider by name (notifications://providers marks them secret): {"token": "…"}. Write-only — never answered back. A secret left out keeps the one saved.'],
            'options' => ['type' => 'object', 'description' => 'The open settings of the provider by name: {"mode": "smtp", "host": "…", "from_address": "…"}.'],
            'recipients' => ['type' => 'array', 'items' => ['type' => ['object', 'string']], 'description' => 'Who gets it, in the provider\'s shape (notifications://providers): Telegram [{"chat_id": "-100…", "label": "Managers"}], e-mail [{"email": "…"}, {"admin_id": 3}]. An addressee of the event: {"variable": "reply_to"}.'],
            'subject' => ['description' => 'E-mail only: the subject, one line, with {{variables}}. A text for the site\'s main language, or {"en": "…", "ru": "…"}.'] + $texts,
            'template' => ['description' => 'The message, Mustache: {{name}}, {{#list}}…{{/list}}, {{#name}}…{{/name}}, {{^name}}…{{/name}}; no partials, no delimiter changes. A text for the site\'s main language, or {"en": "…", "ru": "…"}. A letter\'s template that starts with a tag is HTML (notifications://providers lists its tags). Left out on create — the event\'s default template.'] + $texts,
            'default_template' => ['type' => 'boolean', 'description' => 'Put the event\'s default template for this provider (notifications_default_template shows it) in place of subject and template, in every language it has. A new channel of an event without a template gets it anyway.'],
            'enabled' => ['type' => 'boolean', 'description' => 'Sends at once. False when omitted — pass true only when a person asked for that.'],
        ];

        return Arguments::strictAll([
            Tool::read(
                'channels_list',
                'The channels, the newest first: name, provider, event, how many recipients, enabled, which secrets are '
                .'set and the last delivery. Read this (or notifications://channels) before creating one, so that the same '
                .'people are not notified twice.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'provider' => ['type' => 'string', 'description' => 'Only this provider: telegram, email.'],
                    'event' => ['type' => 'string', 'description' => 'Only channels of this event; "none" for the channels for any event.'],
                    'enabled' => ['type' => 'boolean', 'description' => 'Only switched on (true) or off (false).'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin, newest first.'],
                ]],
                permission: [NotificationsModule::VIEW, NotificationsModule::MANAGE],
            ),

            Tool::read(
                'channel_get',
                'One channel in full: its recipients, subject and template in every language, open settings, and which '
                .'secrets are set (credentials_set) — never their values.',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => ['channel' => $channel], 'required' => ['channel']],
                permission: [NotificationsModule::VIEW, NotificationsModule::MANAGE],
            ),

            Tool::mutating(
                'channel_create',
                self::WARNING.'Create a channel. Switched off unless enabled: true is passed; a channel cannot be switched '
                .'on until it has its secrets and recipients. The answer carries warnings that do not stop the save — '
                .'a {{name}} no event has, on a channel for any event.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->create($arguments)),
                ['properties' => [
                    'provider' => ['type' => 'string', 'description' => 'telegram, email, or another of notifications://providers. Chosen once: it cannot be changed later.'],
                ] + $fields, 'required' => ['name', 'provider']],
            ),

            Tool::mutating(
                'channel_update',
                self::WARNING.'Change a channel: any field of notifications_channel_create but provider; what is left out '
                .'keeps what it had. A secret is replaced by sending a new one in credentials and wiped with forget.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->update($arguments)),
                ['properties' => ['channel' => $channel] + $fields + [
                    'provider' => ['type' => 'string', 'description' => 'Cannot be changed: a channel of another provider is a new channel (notifications_channel_copy will not do either). Passing a different one is refused.'],
                    'forget' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Secrets to wipe, by name: ["password"].'],
                ], 'required' => ['channel']],
            ),

            Tool::mutating(
                'channel_delete',
                self::WARNING.'Put a channel in the bin: it sends nothing from now on, and its id stays in the consumers that '
                .'name it — the panel brings it back with everything it had.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['channel' => $channel], 'required' => ['channel']],
            ),

            Tool::mutating(
                'channel_copy',
                self::WARNING.'A new channel with the settings of this one — its secrets carried over encrypted on the '
                .'server, never through the agent — switched off. For a second chat or another language of the same message.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->copy($arguments)),
                ['properties' => [
                    'channel' => $channel,
                    'name' => ['type' => 'string', 'description' => 'The copy\'s name; "<name> (copy)" when omitted.'],
                ], 'required' => ['channel']],
            ),

            Tool::read(
                'default_template',
                'The default template an event declares for a provider — the subject and the message in every language '
                .'of the site that the event has words in (the main language always) — and the message it makes on the '
                .'example values. A new channel of the event starts with it; default_template: true on '
                .'notifications_channel_update puts it back on a channel.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->defaultTemplate($arguments)),
                ['properties' => [
                    'event' => ['type' => 'string', 'description' => 'The key of a declared event (notifications://events).'],
                    'provider' => ['type' => 'string', 'description' => 'telegram, email, or another of notifications://providers.'],
                    'locale' => ['type' => 'string', 'description' => 'The language of the rendered example; the site\'s main language when omitted.'],
                ], 'required' => ['event', 'provider']],
                permission: [NotificationsModule::VIEW, NotificationsModule::MANAGE],
            ),

            Tool::read(
                'preview',
                'The message as it would go, on the example values of the event — sent nowhere, saved nowhere. Give a saved '
                .'channel, or provider (and event, subject, template) to try a template before it is saved; both — the '
                .'given fields over the saved channel. A template Mustache cannot read is refused with what to fix.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->preview($arguments)),
                ['properties' => [
                    'channel' => ['type' => 'integer', 'description' => 'A saved channel to preview.'],
                    'provider' => ['type' => 'string', 'description' => 'The provider of a template not saved yet.'],
                    'event' => $fields['event'],
                    'subject' => $fields['subject'],
                    'template' => $fields['template'],
                    'locale' => ['type' => 'string', 'description' => 'The language to render in, as an event would bring it; the site\'s main language when omitted.'],
                ]],
                permission: [NotificationsModule::VIEW, NotificationsModule::MANAGE],
            ),

            Tool::mutating(
                'test',
                self::WARNING.'Send the channel\'s message now, past the queue, with the example values of its event — to every '
                .'fixed recipient, or the one named. Works on a switched-off channel. Ten a minute. The answer is per '
                .'recipient: sent, or the provider\'s error.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->test($arguments, $user)),
                ['properties' => [
                    'channel' => $channel,
                    'recipient' => ['type' => 'string', 'description' => 'Only this recipient: a chat id or an address of the channel.'],
                ], 'required' => ['channel']],
            ),

            Tool::mutating(
                'find_chats',
                self::WARNING.'Telegram: the chats the bot was written to lately (private chats, groups, channels), to pick '
                .'chat ids from. With a saved channel\'s token, or a token the person gave — used for this call, written '
                .'nowhere. Nothing is sent. A bot with a webhook gives no updates — enter chat ids by hand then.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->findChats($arguments)),
                ['properties' => [
                    'channel' => ['type' => 'integer', 'description' => 'A saved Telegram channel whose token to use.'],
                    'token' => ['type' => 'string', 'description' => 'A bot token the person gave, when there is no channel yet.'],
                ]],
            ),

            Tool::read(
                'deliveries',
                'The delivery log, the newest first: when, event, channel, recipient, status (queued, sent, failed, '
                .'skipped), the reason of a skip, the error, attempts, and the values the event brought — what '
                .'visitors typed. A failed row is sent again with notifications_retry.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->deliveries($arguments)),
                ['properties' => [
                    'channel' => ['type' => 'integer', 'description' => 'Only this channel.'],
                    'event' => ['type' => 'string', 'description' => 'Only this event.'],
                    'status' => ['type' => 'string', 'enum' => Delivery::STATUSES, 'description' => 'Only this status.'],
                    'subject_type' => ['type' => 'string', 'description' => 'With subject_id: only what was sent about this record.'],
                    'subject_id' => ['type' => 'integer', 'description' => 'The record\'s id.'],
                    'since' => ['type' => 'string', 'description' => 'From this date or moment, ISO 8601.'],
                    'tests' => ['type' => 'boolean', 'description' => 'Include tests; true when omitted.'],
                    'limit' => ['type' => 'integer', 'description' => 'How many rows, up to 200; 50 when omitted.'],
                ]],
                permission: [NotificationsModule::VIEW, NotificationsModule::MANAGE],
            ),

            Tool::mutating(
                'retry',
                self::WARNING.'Send a failed (or stuck queued) delivery again: the same values, the template as it is now. '
                .'The row goes back to queued and its attempts grow.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->retry($arguments)),
                ['properties' => [
                    'delivery' => ['type' => 'integer', 'description' => 'The id of a log row; notifications_deliveries has them.'],
                ], 'required' => ['delivery']],
            ),
        ], 'notifications_');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = Channel::query();

        if (($arguments['trashed'] ?? false) === true) {
            $query->onlyTrashed()->orderByDesc('deleted_at');
        }

        if (is_string($arguments['provider'] ?? null) && $arguments['provider'] !== '') {
            $query->where('provider', $arguments['provider']);
        }

        if (is_string($arguments['event'] ?? null) && $arguments['event'] !== '') {
            $arguments['event'] === 'none' ? $query->whereNull('event') : $query->where('event', $arguments['event']);
        }

        if (is_bool($arguments['enabled'] ?? null)) {
            $query->where('enabled', $arguments['enabled']);
        }

        return [
            'environment' => app()->environment(),
            'channels' => $query->orderByDesc('id')->get()
                ->map(fn (Channel $one): array => $this->form()->row($one))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        return $this->describe($this->channel($arguments['channel'] ?? null));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments): array
    {
        $values = $this->fields($arguments);
        $this->provider(is_string($values['provider'] ?? null) ? $values['provider'] : '');
        $this->event($values['event'] ?? null);

        $work = function () use ($values): array {
            $saved = $this->form()->save(new Channel, $values);

            return $this->describe($saved['channel'], $saved['warnings']);
        };

        if (! $this->dryRun($arguments)) {
            return $work();
        }

        $would = $this->rehearse($work);
        // The id the rehearsal was given is nobody's once it is rolled back.
        $would['channel']['id'] = null;

        return ['dry_run' => true] + $would;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments): array
    {
        $channel = $this->channel($arguments['channel'] ?? null);
        $values = $this->fields($arguments);

        if ($values === []) {
            throw new ToolFailure('Nothing to change: pass at least one field of notifications_channel_create. notifications_channel_get says what the channel has.');
        }

        if ($channel->trashed()) {
            throw new ToolFailure("Channel {$channel->id} is in the bin. Bring it back in the panel before editing it.");
        }

        if (array_key_exists('provider', $values) && $values['provider'] !== $channel->provider) {
            throw new ToolFailure('`provider` cannot be changed. Copy the settings into a new channel instead.');
        }

        if (array_key_exists('event', $values)) {
            $this->event($values['event']);
        }

        $work = function () use ($channel, $values): array {
            $saved = $this->form()->save($channel, $values);

            return $this->describe($saved['channel'], $saved['warnings']);
        };

        if (! $this->dryRun($arguments)) {
            return $work();
        }

        return ['dry_run' => true, 'fields' => array_keys($values)] + $this->rehearse($work);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $channel = $this->channel($arguments['channel'] ?? null);

        if ($channel->trashed()) {
            return ['trashed' => true, 'id' => $channel->id, 'already' => true];
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->form()->row($channel)];
        }

        $channel->delete();

        return ['trashed' => true, 'id' => $channel->id];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function copy(array $arguments): array
    {
        $channel = $this->channel($arguments['channel'] ?? null);
        $name = is_string($arguments['name'] ?? null) ? $arguments['name'] : null;

        if ($channel->trashed()) {
            throw new ToolFailure("Channel {$channel->id} is in the bin. Bring it back in the panel before copying it.");
        }

        $work = fn (): array => $this->describe($this->form()->copy($channel, $name));

        if (! $this->dryRun($arguments)) {
            return $work();
        }

        $would = $this->rehearse($work);
        $would['channel']['id'] = null;

        return ['dry_run' => true] + $would;
    }

    /**
     * The message on the example values: a saved channel, the given fields over it, or a template
     * not saved at all. The values are checked the way a save checks them, so a template that
     * would be refused is refused here — with the same words.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function preview(array $arguments): array
    {
        $saved = isset($arguments['channel']) ? $this->channel($arguments['channel']) : null;
        $given = array_intersect_key($arguments, array_flip(['provider', 'event', 'subject', 'template', 'default_template']));

        if ($saved === null) {
            $this->provider(is_string($given['provider'] ?? null) ? $given['provider'] : '');
        } elseif (array_key_exists('provider', $given) && $given['provider'] !== $saved->provider) {
            throw new ToolFailure('`provider` cannot be changed. Copy the settings into a new channel instead.');
        }

        if (array_key_exists('event', $given)) {
            $this->event($given['event']);
        }

        $subject = $given === [] && $saved !== null ? $saved : $this->form()->unsaved($given, $saved);
        $provider = $this->provider($subject->provider);
        $event = $this->notifications()->eventOf($subject);
        $locale = is_string($arguments['locale'] ?? null) ? $arguments['locale'] : null;
        $rendered = $this->container->make(Renderer::class)->render($subject, $provider, $event?->examples() ?? [], $locale, $event);

        return [
            'provider' => $subject->provider,
            'event' => $subject->event,
            'subject' => $rendered['message']->subject,
            'body' => $rendered['message']->body,
            'plain' => $rendered['message']->plain,
            'truncated' => $rendered['message']->truncated,
            'unknown_variables' => $rendered['unknown'],
            'values' => $event?->examples() ?? [],
        ];
    }

    /**
     * The event's default template for a provider, and what it renders to on the examples — the
     * same check a save runs, so a default that a site's translation broke says so here.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function defaultTemplate(array $arguments): array
    {
        $provider = $this->provider(is_string($arguments['provider'] ?? null) ? $arguments['provider'] : '');
        $event = $this->event($arguments['event'] ?? null)
            ?? throw new ToolFailure('`event` cannot be empty: a default template is an event\'s — notifications://events.');
        $sample = $this->form()->sample($event, $provider->key())
            ?? throw new ToolFailure("Event [{$event->key}] has no default template for [{$provider->key()}]: write one, notifications://events has its variables.");

        return [
            'event' => $event->key,
            'provider' => $provider->key(),
            'subject' => $sample['subject'],
            'template' => $sample['template'],
            'html' => $provider instanceof WritesHtml && $provider->isHtml((string) reset($sample['template'])),
            'preview' => $this->preview([
                'provider' => $provider->key(),
                'event' => $event->key,
                'locale' => $arguments['locale'] ?? null,
                'default_template' => true,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function test(array $arguments, ?Authenticatable $user): array
    {
        $channel = $this->channel($arguments['channel'] ?? null);
        $only = is_string($arguments['recipient'] ?? null) && $arguments['recipient'] !== '' ? $arguments['recipient'] : null;

        if ($channel->trashed()) {
            throw new ToolFailure("Channel {$channel->id} is in the bin. Bring it back in the panel before testing it.");
        }

        $provider = $this->providers()->find($channel->provider)
            ?? throw new ToolFailure("Provider [{$channel->provider}] of channel {$channel->id} is not registered. Providers: {$this->providerList()} — notifications://providers.");

        $event = $this->notifications()->eventOf($channel);
        $fixed = array_values(array_filter($channel->recipientList(), static fn (array $one): bool => ! isset($one['variable'])));
        $recipients = array_values(array_filter(
            $provider->resolve($fixed, $event?->examples() ?? []),
            static fn (Recipient $one): bool => $only === null || $one->key === $only,
        ));

        if ($recipients === []) {
            throw new ToolFailure($only === null
                ? "Channel {$channel->id} has no fixed recipients to test: an addressee of the event is a visitor, never written to by a test."
                : "Channel {$channel->id} has no recipient [{$only}]. notifications_channel_get lists them.");
        }

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_send_to' => array_map(static fn (Recipient $one): array => [
                    'recipient' => $one->key,
                    'label' => $one->label,
                    'skipped' => $one->skip,
                ], $recipients),
            ];
        }

        $key = 'webx-notifications:test:'.($user?->getAuthIdentifier() ?? 'mcp');
        $limit = max(1, (int) $this->config()->get('webx-notifications.tests_per_minute', 10));

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            throw new ToolFailure((string) __('webx-notifications::errors.tests-too-many', ['seconds' => RateLimiter::availableIn($key)]));
        }

        RateLimiter::hit($key, 60);

        $results = $this->notifications()->test($channel, $only);
        $mail = $this->form()->mail($channel);

        return [
            'results' => $results,
            // A test a trap took went nowhere, whatever its status says.
            'warnings' => array_map(static fn (array $warning): string => $warning['message'], $mail['warnings'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function findChats(array $arguments): array
    {
        $token = is_string($arguments['token'] ?? null) ? trim($arguments['token']) : '';
        $channel = isset($arguments['channel']) ? $this->channel($arguments['channel']) : null;

        if ($channel === null && $token === '') {
            throw new ToolFailure('Give `channel` (a saved Telegram channel) or `token` (a bot token the person gave).');
        }

        $key = $channel->provider ?? 'telegram';
        $provider = $this->providers()->find($key);

        if (! $provider instanceof FindsRecipients) {
            throw new ToolFailure("Provider [{$key}] cannot look for recipients; only Telegram can. Enter them by hand.");
        }

        $input = $token === '' ? [] : ['credentials' => ['token' => $token]];

        if ($channel === null) {
            $input['provider'] = $key;
        }

        $subject = $this->form()->unsaved($input, $channel);
        $secrets = $subject->secrets();

        if ($secrets->names() === []) {
            throw new ToolFailure("Channel {$channel?->id} has no token. Ask the person for the bot's token and pass it as `token`.");
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_ask' => 'Telegram getUpdates of the bot, without confirming them: nothing is sent and nothing is written.'];
        }

        try {
            $found = $provider->find($secrets);
        } catch (DeliveryFailed $failed) {
            throw new ToolFailure($secrets->mask($failed->getMessage()));
        }

        return [
            'chats' => array_map(static fn (FoundRecipient $one): array => $one->toArray(), $found),
            'hint' => $found === [] ? (string) __('webx-notifications::errors.telegram.nothing-found') : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deliveries(array $arguments): array
    {
        $query = Delivery::query()->orderByDesc('id');

        foreach (['channel' => 'channel_id', 'subject_id' => 'subject_id'] as $argument => $column) {
            if (is_int($arguments[$argument] ?? null)) {
                $query->where($column, $arguments[$argument]);
            }
        }

        foreach (['event', 'status', 'subject_type'] as $column) {
            if (is_string($arguments[$column] ?? null) && $arguments[$column] !== '') {
                $query->where($column, $arguments[$column]);
            }
        }

        if (is_string($arguments['since'] ?? null) && $arguments['since'] !== '') {
            try {
                $since = Carbon::parse($arguments['since'])->setTimezone((string) config('app.timezone'));
            } catch (Throwable) {
                throw new ToolFailure("`since` is not a date: [{$arguments['since']}]. Give it as 2026-10-01 or 2026-10-01T09:00:00Z.");
            }

            $query->where('created_at', '>=', $since);
        }

        if (($arguments['tests'] ?? true) === false) {
            $query->where('is_test', false);
        }

        $limit = is_int($arguments['limit'] ?? null) ? min(200, max(1, $arguments['limit'])) : self::LIMIT;
        $rows = $query->limit($limit + 1)->get();
        $log = $this->container->make(DeliveryController::class);

        return [
            'deliveries' => $rows->take($limit)->map(static fn (Delivery $row): array => $log->row($row))->values()->all(),
            'more' => $rows->count() > $limit,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function retry(array $arguments): array
    {
        $reference = $arguments['delivery'] ?? null;

        if (! is_int($reference) && ! (is_string($reference) && ctype_digit($reference))) {
            throw new ToolFailure('A delivery is its id, a number. notifications_deliveries has them.');
        }

        $delivery = Delivery::query()->find((int) $reference)
            ?? throw new ToolFailure("No delivery has the id [{$reference}]. notifications_deliveries has them.");
        $log = $this->container->make(DeliveryController::class);

        if (! $delivery->retryable() || $delivery->is_test || $delivery->channel_id === null) {
            throw new ToolFailure("Delivery {$delivery->id} was {$delivery->status}; only failed or stuck deliveries of a channel that still exists are retried, tests never.");
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_retry' => $log->row($delivery)];
        }

        try {
            return $log->row($this->notifications()->retry($delivery));
        } catch (Refusal $refusal) {
            throw new ToolFailure($refusal->getMessage());
        }
    }

    /**
     * A channel and its form, the secret fields left out of the values: the agent is told which
     * are set, and an empty string for each would read as "not set".
     *
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function describe(Channel $channel, array $warnings = []): array
    {
        $described = $this->form()->describe($channel, $warnings);
        $secret = [];

        foreach ($this->providers()->find($channel->provider)?->fields() ?? [] as $field) {
            if ($field->secret) {
                $secret[$field->name] = true;
            }
        }

        $described['values'] = array_diff_key($described['values'], $secret);

        // What the agent has to say to the person in the same list as what saving found: the
        // channel's letters go to a trap, or the settings are not in effect yet.
        foreach ($described['mail']['warnings'] ?? [] as $warning) {
            $described['warnings'][] = $warning['message'];
        }

        return $described;
    }

    /**
     * The fields of a channel among the arguments, as the form takes them.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function fields(array $arguments): array
    {
        return array_intersect_key($arguments, array_flip([
            'name', 'provider', 'event', 'credentials', 'options', 'recipients', 'subject', 'template', 'default_template', 'enabled', 'forget',
        ]));
    }

    private function provider(string $key): Provider
    {
        if ($key === '') {
            throw new ToolFailure("`provider` cannot be empty. Providers: {$this->providerList()} — notifications://providers.");
        }

        return $this->providers()->find($key)
            ?? throw new ToolFailure("Provider [{$key}] is not registered. Providers: {$this->providerList()} — notifications://providers.");
    }

    private function event(mixed $key): ?EventDefinition
    {
        if ($key === null || $key === '') {
            return null;
        }

        $events = $this->container->make(NotificationEvents::class);
        $event = is_string($key) ? $events->find($key) : null;

        if ($event === null) {
            $declared = implode(', ', array_map(static fn (EventDefinition $one): string => $one->key, $events->all()));
            $shown = is_scalar($key) ? (string) $key : '';

            throw new ToolFailure("Event [{$shown}] is not declared. Events: ".($declared === '' ? 'none yet' : $declared).' — notifications://events.');
        }

        return $event;
    }

    private function providerList(): string
    {
        return implode(', ', $this->providers()->keys());
    }

    private function channel(mixed $reference): Channel
    {
        if (! is_int($reference) && ! (is_string($reference) && ctype_digit($reference))) {
            throw new ToolFailure('A channel is its id, a number. notifications_channels_list has them all.');
        }

        $channel = Channel::withTrashed()->find((int) $reference);

        return $channel instanceof Channel
            ? $channel
            : throw new ToolFailure("No channel has the id [{$reference}]. notifications_channels_list has them all.");
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function rehearse(Closure $work): mixed
    {
        return Rehearsal::run($work, (new Channel)->getConnectionName());
    }

    /**
     * Run a call, and turn a refusal of the form into something the agent can read: the field and
     * the panel's words for it — a template's included, which say what Mustache could not read.
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function form(): ChannelForm
    {
        return $this->container->make(ChannelForm::class);
    }

    private function providers(): Providers
    {
        return $this->container->make(Providers::class);
    }

    private function notifications(): Notifications
    {
        return $this->container->make(Notifications::class);
    }

    private function config(): Config
    {
        return $this->container->make(Config::class);
    }
}
