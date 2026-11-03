<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use RuntimeException;

/** A refusal of the delivery module, worded for the person or the agent who asked. */
class DeliveryException extends RuntimeException {}
