<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Nova Poshta branch',
            'description' => 'Pick up at a branch of the carrier; you pay the carrier on receipt.',
        ],
        'locker' => [
            'title' => 'Nova Poshta parcel locker',
            'description' => 'Pick up from a parcel locker of the carrier; you pay the carrier on receipt.',
        ],
        'courier' => [
            'title' => 'Nova Poshta courier',
            'description' => 'The carrier brings the parcel to your door; you pay the carrier on receipt.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Payment for delivery',
        'payment_methods' => [
            'Cash' => 'Cash',
            'NonCash' => 'Non-cash (under a contract with the carrier)',
        ],
        'cargo_type' => 'Cargo type by default',
        'cargo_types' => [
            'Parcel' => 'Parcel',
            'Cargo' => 'Cargo',
            'Documents' => 'Documents',
        ],
        'weight' => 'Weight by default, kg',
        'description' => 'Description of the cargo by default',
        'description_default' => 'Goods',
        'sender' => 'Sender',
        'contact' => 'Contact person',
        'sender_city' => 'Town of sending',
        'sender_point' => 'Branch of sending',
        'locker_width' => 'Parcel locker: width by default, cm',
        'locker_height' => 'Parcel locker: height by default, cm',
        'locker_length' => 'Parcel locker: length by default, cm',
    ],
    'credentials' => [
        'api_key' => 'API key',
    ],
    'connection' => [
        'ok' => 'Senders in the account: :count',
        'key_refused' => 'The carrier did not accept the API key.',
        'no_answer' => 'The carrier did not answer.',
        'no_sender' => 'The carrier\'s account has no sender.',
        'no_key' => 'Enter the API key first.',
    ],
    'waybill' => [
        'weight' => 'Weight, kg',
        'seats' => 'Seats',
        'cargo_type' => 'Cargo type',
        'description' => 'Description of the cargo',
        'date' => 'Date of sending',
        'width' => 'Width, cm',
        'height' => 'Height, cm',
        'length' => 'Length, cm',
    ],
    'errors' => [
        'no_cod' => 'Cash on delivery is not supported by this carrier yet.',
        'name_empty' => 'Fill in the recipient\'s first and last name.',
        'name_letters' => 'The carrier takes the name in Ukrainian letters only.',
        'phone' => 'The carrier takes a Ukrainian mobile number: +380 and nine digits.',
        'no_sender' => 'Choose the sender, the contact person, the town and the branch of sending in the carrier\'s settings first.',
        'contact_gone' => 'The contact person of the settings is no longer in the carrier\'s account: choose another one.',
        'no_town' => 'The carrier does not know the town of the order: bind it in the queue of towns.',
        'no_point' => 'The order has no branch or parcel locker of the carrier.',
        'no_street' => 'The order has no street of the carrier or no house number.',
        'weight' => 'The weight is more than zero.',
        'weight_too_much' => 'The weight is more than the point takes.',
        'seats' => 'Seats are a whole number from one.',
        'locker_seats' => 'A parcel locker takes one seat.',
        'cargo_type' => 'Choose a cargo type from the list.',
        'size' => 'The sizes are more than zero.',
        'locker_size' => 'A parcel locker takes up to 40 × 30 × 60 cm.',
        'date' => 'The date of sending is today or a day up to three months ahead.',
        'read_only' => 'This site uses the carrier\'s key read only: waybills are off.',
        'no_ref' => 'The carrier\'s id of this waybill is not kept: cancel it in the carrier\'s account.',
        'carrier_said' => 'The carrier refused: :text',
    ],
    'remarks' => [
        'novaposhta_service_point' => 'Skipped, service points of the carrier: :count',
        'novaposhta_deny_to_select' => 'Skipped, closed for choosing: :count',
        'novaposhta_invalid_point' => 'Skipped, with incomplete data: :count',
    ],
];
