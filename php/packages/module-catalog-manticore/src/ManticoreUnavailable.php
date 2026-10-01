<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

use RuntimeException;

/**
 * The server does not answer — refused, timed out, or not itself. Remembered for a while, and the
 * catalogue falls back on the database or answers 503 (decisions 10–14 of the Manticore spec).
 */
final class ManticoreUnavailable extends RuntimeException {}
