<?php

declare(strict_types=1);

namespace WebxUi\Geography;

/**
 * The closed list of locality types (§4.2). A carrier's own word for a type it cannot place is
 * `Other`; the word itself is the satellite's business, never a new case here.
 */
enum LocalityKind: string
{
    case City = 'city';
    case Town = 'town';
    case Township = 'township';
    case Village = 'village';
    case Settlement = 'settlement';
    case Other = 'other';

    /**
     * The share of `weight` the type gives a locality, so that among places with no points yet
     * a city comes before a village in the suggestions. Points add one each on top (§4.1).
     */
    public function weight(): int
    {
        return match ($this) {
            self::City => 100,
            self::Town => 50,
            self::Township => 20,
            self::Village, self::Settlement => 10,
            self::Other => 0,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }
}
