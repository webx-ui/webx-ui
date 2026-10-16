<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Mcp\McpResource;
use WebxUi\Notifications\Channels\ChannelForm;
use WebxUi\Notifications\Contracts\FindsRecipients;
use WebxUi\Notifications\Contracts\Provider;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Events\Variable;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Notifications;
use WebxUi\Notifications\Providers;
use WebxUi\Notifications\Sending\Field;
use WebxUi\Notifications\Telegram\TelegramMarkup;

/**
 * What an agent reads before it writes a channel (§12.3): the providers and their settings, the
 * events and what their templates may use, and the channels there are — so that a second channel
 * of the same purpose is not made by accident.
 */
final class NotificationsResources
{
    /** How each provider of the box takes its recipients; a provider of a site describes its own in its guide. */
    private const RECIPIENTS = [
        'telegram' => '[{"chat_id": "<number, groups start with -100, or @channel>", "label": "<optional>"}] — notifications_find_chats lists the chats the bot knows.',
        'email' => '[{"email": "<address>"}, {"admin_id": <id of an administrator of the panel>}] — an administrator gets the address the panel has for them at the moment of sending.',
    ];

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'notifications://providers',
                'Notification providers',
                'The kinds of channel the site has — telegram, email and any a package added: their settings (which are '
                .'secret: write-only), the shape of their recipients, whether a message has a subject, the markup a '
                .'template may carry, and whether the provider can find recipients itself.',
                fn (): array => $this->providers(),
            ),
            new McpResource(
                'notifications://events',
                'Notification events',
                'What modules notify about: each event\'s variables with examples, its lists and their fields, its '
                .'addressees (variables that are an address a channel may write to), the sample a new channel starts '
                .'with, the template syntax, and how many channels each event already has.',
                fn (): array => $this->events(),
            ),
            new McpResource(
                'notifications://channels',
                'Notification channels',
                'Every channel in short — provider, event, recipients, enabled, which secrets are set — and how the site '
                .'sends: through the queue or after the response.',
                fn (): array => $this->channels(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function providers(): array
    {
        return [
            'providers' => array_values(array_map(fn (Provider $provider): array => [
                'key' => $provider->key(),
                'label' => (string) __($provider->label()),
                'settings' => array_map(static fn (Field $field): array => $field->toArray(), $provider->fields()),
                'recipients' => self::RECIPIENTS[$provider->key()] ?? 'See the guide of the package that adds this provider.',
                'addressee' => $provider->address(),
                'subject' => $provider->hasSubject(),
                'markup' => $this->markup($provider->key()),
                'limit' => $provider->limit(),
                'find_recipients' => $provider instanceof FindsRecipients,
            ], $this->container->make(Providers::class)->all())),
            'secrets' => 'A setting with secret: true goes in `credentials` and is never answered back; the others in `options`.',
        ];
    }

    /** The markup a template may carry; null for a provider of a site, which says it in its guide. */
    private function markup(string $provider): ?string
    {
        return match ($provider) {
            'telegram' => 'Telegram HTML, tags: '.implode(', ', array_keys(TelegramMarkup::TAGS)).'. Values are escaped; a tag opened inside a section closes in it.',
            'email' => 'Plain text: the site frames it in a letter and escapes the whole body.',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function events(): array
    {
        $counts = Channel::query()
            ->selectRaw('event, count(*) as total, sum(case when enabled then 1 else 0 end) as enabled')
            ->groupBy('event')
            ->get()
            ->keyBy(static fn (Channel $row): string => (string) $row->event);
        $count = static fn (string $key, string $column): int => (int) ($counts->get($key)?->getAttribute($column) ?? 0);

        return [
            'syntax' => [
                'engine' => 'Mustache, without partials ({{>x}}), delimiter changes ({{=<% %>=}}), pragmas or blocks.',
                'value' => '{{name}} — escaped for the provider; {{{name}}} and {{&name}} are the same as {{name}}.',
                'list' => '{{#items}}…{{/items}} goes over the rows of a list; inside, {{field}} is a field of the row, then a variable of the event.',
                'present' => '{{#name}}…{{/name}} when the value is there, {{^name}}…{{/name}} when it is empty.',
                'names' => 'Lowercase letters, digits and _, one dot at most: field.phone.',
                'languages' => 'A template is a text per language; a message goes in the language the event brings, else the site\'s main language, else any written.',
                'check' => 'A channel of an event refuses a name the event does not have; a channel for any event only warns. notifications_preview renders without sending.',
            ],
            'events' => array_values(array_map(static fn (EventDefinition $event): array => [
                ...$event->toArray(),
                'lists' => array_values(array_map(static fn (Variable $variable): string => $variable->name, array_filter($event->variables, static fn (Variable $variable): bool => $variable->isList()))),
                'addressees' => array_map(static fn (Variable $variable): array => ['name' => $variable->name, 'address' => $variable->address], $event->addressees()),
                'channels' => $count($event->key, 'total'),
                'enabled' => $count($event->key, 'enabled'),
            ], $this->container->make(NotificationEvents::class)->all())),
            'any_event' => ['channels' => $count('', 'total'), 'enabled' => $count('', 'enabled')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function channels(): array
    {
        $form = $this->container->make(ChannelForm::class);
        $notifications = $this->container->make(Notifications::class);

        return [
            'environment' => app()->environment(),
            'mode' => $notifications->mode(),
            'channels' => Channel::query()->orderByDesc('id')->get()
                ->map(static fn (Channel $channel): array => $form->row($channel))
                ->values()
                ->all(),
        ];
    }
}
