<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Api;

/**
 * A successful answer of the carrier's API (§3.4): `success` was true and `errors` empty.
 *
 * Only `success`, `data` and `errors` are confirmed by the documentation; `warnings`,
 * `errorCodes` and `info.totalCount` are read when they are there (checked on the key, stage 3).
 */
final readonly class ApiResponse
{
    /**
     * @param  list<mixed>  $data
     * @param  list<string>  $warnings
     * @param  list<string>  $errors
     * @param  list<string>  $errorCodes
     */
    public function __construct(
        public array $data,
        public array $warnings = [],
        public array $errors = [],
        public array $errorCodes = [],
        public ?int $totalCount = null,
    ) {}

    /**
     * The records of `data` that are objects, as arrays.
     *
     * @return list<array<string, mixed>>
     */
    public function records(): array
    {
        $records = [];

        foreach ($this->data as $record) {
            if (is_array($record)) {
                /** @var array<string, mixed> $record */
                $records[] = $record;
            }
        }

        return $records;
    }
}
