<?php

declare(strict_types=1);

namespace WebxUi\Orders\Notifications;

use Closure;
use Illuminate\Contracts\Container\Container;
use WebxUi\Localization\Locales;
use WebxUi\Notifications\Channels\ChannelForm;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Orders\OrderSettings;
use WebxUi\Settings\Contacts\Contacts;
use WebxUi\Settings\Settings;

/**
 * The channels a site starts with (spec §10.1), made once — then they are the site's own:
 *
 * - to the buyer on `orders.placed`, «Order accepted» (decision 81);
 * - to the shop on `orders.placed`, «New order», to the first e-mail of the contacts of the site,
 *   taken now; with no contacts the channel is made switched off, and `webx:doctor` says so;
 * - to the buyer on `orders.paid`, «Payment received» (decision 43);
 * - to the buyer on `orders.items_changed`, «Order changed», and on `orders.payment_requested`,
 *   «Payment due» (§40.2, question 4 of §44) — made apart from the first three: a site that set
 *   those up before the edits came gets these two all the same.
 *
 * Only on a site that has both the notifications module and `module-settings`: the channels of a
 * milestone are kept in the settings of the section, and without them there is nowhere to keep
 * the choice. A site that saved `orders.notify.placed` or `.paid` has decided the first three, one
 * that saved `.items_changed` or `.payment_requested` the last two — that part is not made.
 */
final readonly class DefaultChannels
{
    /** The channel of the shop's letter — the one `webx:doctor` looks after. */
    public const string ADMIN = 'placed-admin';

    public function __construct(
        private Container $container,
        private OrderChannels $channels,
        private OrderSettings $settings,
    ) {}

    /**
     * @return list<Channel> the channels made, none when the site has its own already
     */
    public function install(): array
    {
        if (! $this->channels->available() || ! $this->container->bound(Settings::class)) {
            return [];
        }

        $stored = $this->settings->stored() ?? [];

        return [...$this->milestones($stored), ...$this->edits($stored)];
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return list<Channel>
     */
    private function milestones(array $stored): array
    {
        if (array_key_exists(OrderEvents::SETTINGS[OrderEvents::PLACED], $stored) || array_key_exists(OrderEvents::SETTINGS[OrderEvents::PAID], $stored)) {
            return [];
        }

        $shop = $this->shopAddress();

        $customer = $this->make('placed-customer', OrderEvents::PLACED, OrderTemplates::CUSTOMER, [['variable' => 'customer_email']], true);
        $admin = $this->make(self::ADMIN, OrderEvents::PLACED, OrderTemplates::MANAGER, $shop === null ? [] : [['email' => $shop]], $shop !== null);
        $paid = $this->make('paid-customer', OrderEvents::PAID, OrderTemplates::CUSTOMER, [['variable' => 'customer_email']], true);

        $this->container->make(Settings::class)->save([
            OrderEvents::SETTINGS[OrderEvents::PLACED] => [$customer->id, $admin->id],
            OrderEvents::SETTINGS[OrderEvents::PAID] => [$paid->id],
        ]);

        return [$customer, $admin, $paid];
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return list<Channel>
     */
    private function edits(array $stored): array
    {
        $changed = OrderEvents::SETTINGS[OrderEvents::ITEMS_CHANGED];
        $requested = OrderEvents::SETTINGS[OrderEvents::PAYMENT_REQUESTED];

        if (array_key_exists($changed, $stored) || array_key_exists($requested, $stored)) {
            return [];
        }

        $buyer = [['variable' => 'customer_email']];
        $edit = $this->make('items-changed-customer', OrderEvents::ITEMS_CHANGED, OrderTemplates::CUSTOMER, $buyer, true);
        $payment = $this->make('payment-requested-customer', OrderEvents::PAYMENT_REQUESTED, OrderTemplates::CUSTOMER, $buyer, true);

        $this->container->make(Settings::class)->save([
            $changed => [$edit->id],
            $requested => [$payment->id],
        ]);

        return [$edit, $payment];
    }

    /** The first e-mail of the contacts of the site (`contacts.emails`), or none. */
    public function shopAddress(): ?string
    {
        if (! class_exists(Contacts::class) || ! $this->container->bound(Contacts::class)) {
            return null;
        }

        $first = $this->container->make(Contacts::class)->emails()[0] ?? null;

        return $first?->address;
    }

    /**
     * @param  list<array<string, mixed>>  $recipients
     */
    private function make(string $key, string $event, string $audience, array $recipients, bool $enabled): Channel
    {
        $locales = $this->container->make(Locales::class);
        $main = $locales->defaultCode();

        $saved = $this->container->make(ChannelForm::class)->save(new Channel, [
            'name' => (string) trans('webx-orders::notifications.channels.'.$key, [], $main),
            'provider' => 'email',
            'event' => $event,
            'recipients' => $recipients,
            'subject' => $this->texts(static fn (string $locale): ?array => OrderTemplates::email($event, $audience, $locale), 'subject', $locales),
            'template' => $this->texts(static fn (string $locale): ?array => OrderTemplates::email($event, $audience, $locale), 'template', $locales),
            'enabled' => $enabled,
        ]);

        return $saved['channel'];
    }

    /**
     * A text in each language of the site there are words in; the main language always has
     * one — the fallback's, or English.
     *
     * @param  Closure(string): (array{subject?: string, template: string}|null)  $make
     * @return array<string, string>
     */
    private function texts(Closure $make, string $field, Locales $locales): array
    {
        $main = $locales->defaultCode();
        $texts = [];

        foreach (array_values(array_unique([$main, ...$locales->codes()])) as $locale) {
            $one = $make($locale);

            if ($one === null && $locale === $main) {
                $one = $make($locales->fallback()) ?? $make('en');
            }

            if (is_string($one[$field] ?? null)) {
                $texts[$locale] = $one[$field];
            }
        }

        return $texts;
    }
}
