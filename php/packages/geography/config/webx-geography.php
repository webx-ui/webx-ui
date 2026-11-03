<?php

declare(strict_types=1);

return [
    /*
     * How far apart, in kilometres, a carrier's locality and a shared one may lie and still be
     * one place: picks one of several namesakes, and binds a town a carrier calls by another
     * type (§4.3, steps 4–5).
     */
    'match_radius_km' => 5,

    /*
     * The leading carrier of a country (§4.4): `'XX' => ['source' => 'carrier-a']`. Its unmatched
     * localities become shared ones; everybody else's go to the binding queue. Unset, the first
     * carrier to sync the country leads it. The delivery module keeps this in the site settings
     * instead, where the panel shows and changes it.
     */
    'countries' => [],
];
