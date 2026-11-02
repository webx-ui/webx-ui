<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use RuntimeException;

/**
 * A record that breaks the contract — no id, an id too long, a kind that is not a key. The
 * satellite's mistake, which its contract test catches; the page is not written and the run
 * fails as a bad response.
 */
final class InvalidRecord extends RuntimeException {}
