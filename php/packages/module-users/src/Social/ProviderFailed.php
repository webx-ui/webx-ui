<?php

declare(strict_types=1);

namespace WebxUi\Users\Social;

use RuntimeException;

/**
 * The provider refused, the visitor cancelled, or the answer is broken (spec §9.4). The visitor
 * is told one sentence whatever it was; the reason stays in the message for the log.
 */
final class ProviderFailed extends RuntimeException {}
