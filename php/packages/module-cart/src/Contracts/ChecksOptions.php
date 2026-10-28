<?php

declare(strict_types=1);

namespace WebxUi\Cart\Contracts;

/** A provider that accepts options; without it a line takes empty options only. */
interface ChecksOptions
{
    /**
     * Whether these options make a valid choice of this record. Called before a write.
     *
     * @param  array<string, mixed>  $options
     * @return list<string> messages in $locale; empty — accepted
     */
    public function checkOptions(int $ref, array $options, string $locale): array;
}
