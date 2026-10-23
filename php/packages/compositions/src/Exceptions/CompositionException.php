<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Exceptions;

use RuntimeException;

/**
 * A tree that cannot be built at all (COMPOSITIONS §3.6): invalid JSON, a file the schema refuses,
 * two bases, `extends` to nothing or in a circle. Compiling stops and the last compiled set stays.
 */
final class CompositionException extends RuntimeException {}
