<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/**
 * What the buyer reads of a method in one context (spec §7.1): its title, and the line under it —
 * the method's description, or the type's own word, such as the carrier's fee for cash on delivery.
 */
final readonly class Presentation
{
    public function __construct(
        public string $title,
        public ?string $note = null,
    ) {}
}
