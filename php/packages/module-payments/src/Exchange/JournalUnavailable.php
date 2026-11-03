<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

use RuntimeException;

/**
 * The journal could not write an event that must not go unwritten: an incoming notification is
 * then answered 503 and not read at all — the gateway sends it again (decision 48).
 */
final class JournalUnavailable extends RuntimeException {}
