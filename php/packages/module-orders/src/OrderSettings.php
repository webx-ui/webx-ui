<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Validation\ValidationException;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Settings\Settings;

/**
 * The settings of the section (spec §4, §5.2, §11.4): the format of the number and the statuses a
 * new order comes into. The screen `orders.settings` writes them where every setting of the site
 * is kept (`orders.number.prefix`, `orders.initial_status`…); a key nobody saved there — and every
 * key on a site without `module-settings` — reads the config `webx-orders` instead.
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

        if ($stored !== null && array_key_exists($key, $stored) && $stored[$key] !== null && $stored[$key] !== '') {
            return $stored[$key];
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

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
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
