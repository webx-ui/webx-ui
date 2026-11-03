<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Api;

use RuntimeException;

/**
 * The carrier answered with `success: false` — HTTP 200 all the same (§3.2). Carried as the
 * previous exception of `CarrierUnavailable`, so that a waybill can show the carrier's own words
 * (§8.7) while a sync only sees «the carrier did not answer».
 *
 * The errors are the carrier's texts about the request, never the request itself.
 */
final class ApiError extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $codes
     */
    public function __construct(
        public readonly string $method,
        public readonly array $errors,
        public readonly array $codes = [],
    ) {
        parent::__construct("Nova Poshta: {$method} answered with an error: ".($errors === [] ? 'no text' : implode('; ', $errors)));
    }
}
