<?php

declare(strict_types=1);

namespace WebxUi\Auth\Keys;

use RuntimeException;

/** Why a key cannot be issued, in words that name the cure. */
final class KeyRefused extends RuntimeException {}
