<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Contracts;

/**
 * A carrier whose points' titles already say where they are: «Branch 21 (up to 30 kg): 8 Main
 * St.» — under such a title the address line «Town A, Main St., 8» only repeats it.
 *
 * Optional on top of {@see Carrier}, so that an older satellite keeps working: without it the
 * line under a point's title in a list (the checkout's, a picker's) is its address.
 */
interface NamesPointLines
{
    /** The line under the title in a list; null — none. */
    public function pointLine(string $title, string $address): ?string;
}
