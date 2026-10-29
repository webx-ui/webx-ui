<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * The number of an order for people (§4): a string made of the id — prefix, start + id, padded.
 * No counter of its own: a table locked for update would line up every checkout of the site and
 * add a third lock to the cart's and the wallet's (§6.3). A changed format leaves the numbers
 * given out as they are — the number is stored.
 */
final class OrderNumbers
{
    public function __construct(private readonly OrderSettings $settings) {}

    public function format(int $id): string
    {
        return self::compose($this->settings->get('orders.number.prefix'), $this->settings->get('orders.number.start'), $this->settings->get('orders.number.pad'), $id);
    }

    /** The number of this id in a format not saved yet — the trial number of the settings (§11.4). */
    public static function compose(mixed $prefix, mixed $start, mixed $pad, int $id): string
    {
        $number = (string) ((is_numeric($start) ? max(0, (int) $start) : 1000) + $id);
        $pad = is_numeric($pad) ? min(20, max(0, (int) $pad)) : 0;

        return (is_string($prefix) ? $prefix : '').str_pad($number, $pad, '0', STR_PAD_LEFT);
    }
}
