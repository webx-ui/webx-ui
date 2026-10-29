<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use Illuminate\Contracts\Config\Repository;

/**
 * The number of an order for people (§4): a string made of the id — prefix, start + id, padded.
 * No counter of its own: a table locked for update would line up every checkout of the site and
 * add a third lock to the cart's and the wallet's (§6.3). A changed format leaves the numbers
 * given out as they are — the number is stored.
 */
final class OrderNumbers
{
    public function __construct(private readonly Repository $config) {}

    public function format(int $id): string
    {
        $prefix = $this->config->get('webx-orders.number.prefix');
        $start = $this->config->get('webx-orders.number.start');
        $pad = $this->config->get('webx-orders.number.pad');

        $number = (string) ((is_numeric($start) ? max(0, (int) $start) : 1000) + $id);
        $pad = is_numeric($pad) ? min(20, max(0, (int) $pad)) : 0;

        return (is_string($prefix) ? $prefix : '').str_pad($number, $pad, '0', STR_PAD_LEFT);
    }
}
