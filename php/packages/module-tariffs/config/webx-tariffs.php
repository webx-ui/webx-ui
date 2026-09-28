<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Currencies
    |---------------------------------------------------------------------------
    |
    | Currencies a tariff may be priced in: ISO code => the symbol the site
    | prints. The first one is what a new tariff starts with. Where the symbol
    | stands — before the number or after it — is the site's template's
    | business, not this list's.
    |
    | A key that is not three capital letters is skipped: the column holds
    | three. A currency taken out of the list does not lock the tariffs that
    | use it: they keep it, and the site prints its code instead of a symbol.
    |
    */

    'currencies' => [
        'USD' => '$',
        'EUR' => '€',
        'UAH' => '₴',
        'PLN' => 'zł',
    ],

    /*
    |---------------------------------------------------------------------------
    | Button looks
    |---------------------------------------------------------------------------
    |
    | Key => label for the panel, as with banners; the label is a plain string
    | or a translation key. The key is what the site's template turns into a
    | class. The first one is the fallback of a button whose variant was taken
    | out of the list.
    |
    */

    'variants' => [
        'primary' => 'Primary',
        'secondary' => 'Secondary',
        'link' => 'Link',
    ],

];
