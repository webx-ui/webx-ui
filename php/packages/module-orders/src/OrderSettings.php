<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Validation\ValidationException;
use WebxUi\Orders\Checkout\Checkout;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Notifications\OrderChannels;
use WebxUi\Settings\Settings;

/**
 * The settings of the section (spec §4, §5.2, §11.4): the format of the number and the statuses a
 * new order comes into. The screen `orders.settings` writes them where every setting of the site
 * is kept (`orders.number.prefix`, `orders.initial_status`, `orders.notify.placed`…); a key nobody saved there — and every
 * key on a site without `module-settings` — reads the config `webx-orders` instead.
 *
 * The tab «Checkout» (§31.2) keeps `orders.checkout.*` the same way, while the checkout is on; a
 * key nobody saved reads its default here — the shop's page is not the config's business.
 */
final readonly class OrderSettings
{
    /** The screen's keys and the config each one falls back on. */
    public const array KEYS = [
        'orders.number.prefix' => 'webx-orders.number.prefix',
        'orders.number.start' => 'webx-orders.number.start',
        'orders.number.pad' => 'webx-orders.number.pad',
        'orders.initial_status' => 'webx-orders.initial_status',
        'orders.initial_status_awaiting_payment' => 'webx-orders.initial_status_awaiting_payment',
        'orders.notify.placed' => 'webx-orders.notify.placed',
        'orders.notify.paid' => 'webx-orders.notify.paid',
        'orders.notify.payment_failed' => 'webx-orders.notify.payment_failed',
        'orders.notify.received' => 'webx-orders.notify.received',
        'orders.notify.cancelled' => 'webx-orders.notify.cancelled',
        'orders.notify.refunded' => 'webx-orders.notify.refunded',
    ];

    /** The tab «Checkout» (§20.3, §23.4, §31.2) and what a key nobody saved reads. */
    public const array CHECKOUT = [
        'orders.checkout.fields.last_name' => 'optional',
        'orders.checkout.fields.phone' => 'required',
        'orders.checkout.fields.email' => 'required',
        'orders.checkout.recipient' => 'on',
        'orders.checkout.comment' => 'on',
        'orders.checkout.terms' => 'notice',
        'orders.checkout.terms_page' => null,
        'orders.checkout.privacy_page' => null,
        'orders.checkout.quick.enabled' => 'off',
        // The words of the dialog after the order, a language each; null — the package's own.
        'orders.checkout.quick.message' => null,
    ];

    /** The channels of the milestones' events (§10.1): ids of channels of the notifications module. */
    public const array NOTIFY = [
        'orders.notify.placed',
        'orders.notify.paid',
        'orders.notify.payment_failed',
        'orders.notify.received',
        'orders.notify.cancelled',
        'orders.notify.refunded',
    ];

    public function __construct(
        private Container $container,
        private Repository $config,
    ) {}

    public function get(string $key): mixed
    {
        // Through the container on every read: the settings drop their cache on a save, and a
        // value kept here would outlive it.
        $stored = $this->stored();

        // A list of channels saved empty is a choice: nobody is told, whatever the config says.
        if ($stored !== null && in_array($key, self::NOTIFY, true) && array_key_exists($key, $stored)) {
            return is_array($stored[$key]) ? $stored[$key] : [];
        }

        if ($stored !== null && array_key_exists($key, $stored) && $stored[$key] !== null && $stored[$key] !== '') {
            return $stored[$key];
        }

        if (array_key_exists($key, self::CHECKOUT)) {
            return self::CHECKOUT[$key];
        }

        return $this->config->get(self::KEYS[$key] ?? 'webx-orders.'.substr($key, strlen('orders.')));
    }

    /**
     * Every key of the screen as the site reads it now.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $all = [];

        foreach (array_keys(self::KEYS) as $key) {
            $all[$key] = $this->get($key);
        }

        return $all;
    }

    /**
     * The keys of the tab «Checkout» as the site reads them now; none while the checkout is off.
     *
     * @return array<string, mixed>
     */
    public function checkout(): array
    {
        if (! Checkout::enabled()) {
            return [];
        }

        $all = [];

        foreach (array_keys(self::CHECKOUT) as $key) {
            $all[$key] = $this->get($key);
        }

        return $all;
    }

    /**
     * The number the next order would get in this format (§4) — the trial number the screen shows.
     *
     * @param  array<string, mixed>  $values  the screen's keys; a key left out reads as saved
     */
    public function sample(array $values = []): string
    {
        $pick = fn (string $key): mixed => array_key_exists($key, $values) ? $values[$key] : $this->get($key);

        return OrderNumbers::compose($pick('orders.number.prefix'), $pick('orders.number.start'), $pick('orders.number.pad'), self::nextId());
    }

    /**
     * Keep the values the screen checked (§11.4). A format whose trial number an order already has
     * is refused: the next order would not get it — `unique` would stop the checkout instead.
     *
     * @param  array<string, mixed>  $values
     *
     * @throws ValidationException
     */
    public function store(array $values): void
    {
        $this->check($values);

        foreach (self::NOTIFY as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = $this->container->make(OrderChannels::class)->existing(OrderChannels::ids($values[$key]));
            }
        }

        if ($this->container->bound(Settings::class) && $values !== []) {
            $this->container->make(Settings::class)->save($values);
        }
    }

    /**
     * What the screen's own rules cannot know: the trial number free, and an initial status
     * active in a live group — read now, not from the list the screen was built with.
     *
     * @param  array<string, mixed>  $values
     *
     * @throws ValidationException
     */
    public function check(array $values): void
    {
        $errors = [];
        $sample = $this->sample($values);

        if (Order::query()->where('number', $sample)->exists()) {
            $errors['orders.number.prefix'] = (string) __('webx-orders::screen.number-taken', ['number' => $sample]);
        }

        foreach (['orders.initial_status', 'orders.initial_status_awaiting_payment'] as $key) {
            $id = $values[$key] ?? null;

            if ($id !== null && $id !== '' && ! OrderStatus::query()->whereKey($id)->where('is_active', true)->whereHas('publicStatus', static fn ($group) => $group->whereNull('deleted_at'))->exists()) {
                $errors[$key] = (string) __('validation.in', ['attribute' => (string) __('webx-orders::screen.'.($key === 'orders.initial_status' ? 'initial-status' : 'initial-awaiting'))]);
            }
        }

        foreach (self::NOTIFY as $key) {
            $ids = $values[$key] ?? [];

            if (! is_array($ids) || count(OrderChannels::ids($ids)) !== count($ids)) {
                $errors[$key] = (string) __('webx-orders::screen.notify-ids');
            }
        }

        // An order needs a phone or an e-mail (§6.1, §20.3): one of the two stays required.
        if (array_key_exists('orders.checkout.fields.phone', $values) || array_key_exists('orders.checkout.fields.email', $values)) {
            $pick = fn (string $key): mixed => array_key_exists($key, $values) ? $values[$key] : $this->get($key);

            if ($pick('orders.checkout.fields.phone') !== 'required' && $pick('orders.checkout.fields.email') !== 'required') {
                $errors['orders.checkout.fields.phone'] = (string) __('webx-orders::checkout.settings.contact-needed');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The settings as the screen and the agent read them: whether the site keeps them, the values,
     * and the trial number of the next order. The screen reads the tab «Checkout» too; the agent
     * has the checkout's own tools for it (§32).
     *
     * @return array{stored: bool, values: array<string, mixed>, next_id: int, sample: string}
     */
    public function describe(bool $checkout = false): array
    {
        return [
            // Without the settings module there is nowhere to keep them: the site's config rules.
            'stored' => $this->stored() !== null,
            'values' => $checkout ? [...$this->all(), ...$this->checkout()] : $this->all(),
            'next_id' => self::nextId(),
            'sample' => $this->sample(),
        ];
    }

    /** The id the next order would get, as far as the table tells: one past the last. */
    public static function nextId(): int
    {
        return (int) Order::query()->max('id') + 1;
    }

    /**
     * The settings of the site as stored, or null without `module-settings` — nowhere to keep them.
     *
     * @return array<string, mixed>|null
     */
    public function stored(): ?array
    {
        return $this->container->bound(Settings::class) ? $this->container->make(Settings::class)->raw() : null;
    }
}
