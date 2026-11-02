<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

/**
 * A carrier's directory the site keeps (§7.1), in the order a night pulls them: points and streets
 * need the carrier's localities. Streets are for a carrier that goes to doors (§8).
 */
enum Directory: string
{
    case Localities = 'localities';
    case Points = 'points';
    case Streets = 'streets';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $directory): string => $directory->value, self::cases());
    }
}
