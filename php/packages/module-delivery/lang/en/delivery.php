<?php

declare(strict_types=1);

// The words of delivery: the module, the kinds of points and localities, the buyer's step and its
// refusals. The shop's own kinds of points are named in the panel, not here.
return [
    'title' => 'Delivery',
    'cache' => 'Delivery methods and carriers',
    'cache_points' => 'Delivery points of carriers',
    'carriers' => [
        'store' => 'The shop',
    ],
    'point_kinds' => [
        'branch' => 'Branch',
        'locker' => 'Parcel locker',
        'store' => 'Store',
        'pickup' => 'Pick-up point',
    ],
    'locality_kinds' => [
        'city' => 'City',
        'town' => 'Town',
        'township' => 'Township',
        'village' => 'Village',
        'settlement' => 'Settlement',
        'other' => 'Locality',
    ],
    'step' => [
        'delivery' => 'Delivery',
        'locality' => 'City or town',
        'locality_placeholder' => 'Start typing the name',
        'where' => 'Where to deliver the order?',
        'choose_locality' => 'Choose a city or town',
        'change' => 'Change',
        'popular' => 'Popular cities',
        'show_methods' => 'Show delivery methods',
        'no_methods' => 'Delivery methods are not available here',
        'free' => 'Free',
        'carrier_rates' => 'By carrier rates',
        'choose_point' => 'Choose a branch',
        'pick_up_here' => 'Pick up here',
        'open_until' => 'Open today until :time',
        'closed_today' => 'Closed today',
        'street' => 'Street',
        'house' => 'House',
        'apartment' => 'Apartment',
        'note' => 'Note for the courier',
        'cost' => 'Delivery cost',
        'points' => 'Points: :count',
    ],
    'hints' => [
        'no_streets' => 'Courier delivery is not available here, choose another method',
    ],
    'errors' => [
        'choose_point' => 'Choose a pick-up point.',
        'point_gone' => 'Pick-up point is no longer available, choose another one.',
        'choose_street' => 'Choose the street from the list.',
        'street_required' => 'Enter the street.',
        'house_required' => 'Enter the house number.',
        'too_long' => 'At most :max characters.',
        'no_quote' => 'Could not calculate delivery, choose another method.',
    ],
    'sync' => [
        'running' => 'Already running, page :page',
        'errors' => [
            'no_answer' => 'The carrier did not answer',
            'key_rejected' => 'The carrier refused the API key',
            'bad_response' => 'The carrier\'s answer was not in the expected format',
            'failed' => 'The sync failed on the site\'s side — see the log',
            'interrupted' => 'The sync was interrupted',
        ],
    ],
    'schedule' => [
        'closed' => 'Closed',
        'around_the_clock' => 'Open 24 hours',
        'until' => 'Until :time',
        'from' => 'Opens at :time',
    ],
];
