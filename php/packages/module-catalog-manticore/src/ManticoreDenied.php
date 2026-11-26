<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

use RuntimeException;

/**
 * The server answered and refused the login: none was sent to a server that asks for one, the
 * password is wrong (401), or the login has no right to what it asked (403). Not an outage — the
 * server is not paused for `down_for`, the next request tries again — and not a mistake in the
 * statement either, so nothing that catches {@see ManticoreError} swallows it. The message never
 * holds the password.
 */
final class ManticoreDenied extends RuntimeException {}
