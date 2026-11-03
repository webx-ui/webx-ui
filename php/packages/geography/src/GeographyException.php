<?php

declare(strict_types=1);

namespace WebxUi\Geography;

use InvalidArgumentException;

/** A manual change the geography refuses: the message says what to fix, in words for a person. */
class GeographyException extends InvalidArgumentException {}
