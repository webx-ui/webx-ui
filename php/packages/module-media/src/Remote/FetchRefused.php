<?php

declare(strict_types=1);

namespace WebxUi\Media\Remote;

use RuntimeException;

/**
 * Why a remote address was not fetched, in words meant for whoever asked — never the transport's
 * own error, which names internal hosts and ports.
 */
final class FetchRefused extends RuntimeException {}
