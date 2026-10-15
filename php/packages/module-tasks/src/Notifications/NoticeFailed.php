<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Notifications;

use RuntimeException;

/** A notice that did not get through, said in words fit for the journal — never with a secret in them. */
final class NoticeFailed extends RuntimeException {}
