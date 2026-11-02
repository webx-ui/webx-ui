<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Shipping;

use WebxUi\Delivery\DeliveryException;

/**
 * The carrier, or the base before it, said no to a waybill (§11.2): the message is its reason in
 * words, shown to the manager as it is.
 */
final class WaybillRefused extends DeliveryException {}
