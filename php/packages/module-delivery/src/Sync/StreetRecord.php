<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

/**
 * A street of a carrier's locality, for its courier (§7.2, §8): "Example", kind `street` — the
 * type word is the kind, not part of the name, so that a prefix finds the street by its name.
 * Named arguments; new fields come last and optional.
 */
final readonly class StreetRecord
{
    /** The kinds a street can be; anything else is written as `other`. */
    public const array KINDS = ['street', 'avenue', 'lane', 'square', 'other'];

    /**
     * @param  string  $name  in the carrier's main language, no type word
     * @param  array<string, string>|null  $names  locale → name
     */
    public function __construct(
        public string $externalId,
        public string $localityExternalId,
        public string $name,
        public string $kind = 'street',
        public ?array $names = null,
    ) {}
}
