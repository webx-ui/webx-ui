<?php

declare(strict_types=1);

return [
    /*
    | The two addresses of LiqPay: the API and the page a buyer pays on. A test and a live payment
    | go to the same ones, only the keys differ (spec §4), so they are no input of a person.
    */
    'api_url' => env('LIQPAY_API_URL', 'https://www.liqpay.ua/api/request'),
    'checkout_url' => env('LIQPAY_CHECKOUT_URL', 'https://www.liqpay.ua/api/3/checkout'),

    /*
    | The version of the API. It decides the algorithm of the signature: 7 is SHA3-256, 3 to 6 are
    | SHA1, and any other pair is refused (spec §3.2). There is no setting of the algorithm.
    */
    'version' => 7,
];
