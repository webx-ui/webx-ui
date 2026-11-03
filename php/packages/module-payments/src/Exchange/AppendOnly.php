<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

use LogicException;

/**
 * Somebody tried to change what the journal wrote, or to delete it (decision 31): the raw half of an
 * event is never changed, its decision is written once, and only `webx:payments:exchange-prune`
 * removes events — older than a year (decision 51).
 */
final class AppendOnly extends LogicException {}
