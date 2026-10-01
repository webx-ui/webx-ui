<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

use RuntimeException;

/**
 * The server answered, and the answer is a refusal: a statement it cannot read, a table that is
 * not there. A mistake to report, not an outage to wait out.
 */
final class ManticoreError extends RuntimeException {}
