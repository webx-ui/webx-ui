<?php

declare(strict_types=1);

namespace WebxUi\Routing\Exceptions;

use RuntimeException;

/** Something is wired wrong — an unregistered type, a formatter a model cannot satisfy. */
class RoutingException extends RuntimeException {}
