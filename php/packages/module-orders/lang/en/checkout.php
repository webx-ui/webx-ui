<?php

declare(strict_types=1);

/*
 * The checkout (spec §33). The words of the page, its steps and the «Thanks» come with their
 * stages; the tab «Checkout» of the settings is here now.
 */
return [
    'settings' => [
        'tab' => 'Checkout',
        'fields' => 'Buyer\'s contacts',
        'fields-help' => 'The first name is always asked. An order needs a phone or an e-mail, so at least one of them stays required.',
        'last-name' => 'Last name',
        'phone' => 'Phone',
        'email' => 'E-mail',
        'recipient' => '«The recipient is someone else»',
        'recipient-help' => 'A checkbox under the contacts for an order someone else picks up.',
        'comment' => 'Comment',
        'comment-help' => 'A field where the buyer leaves a comment on the order.',
        'terms-card' => 'Terms',
        'terms' => 'Agreement with the terms',
        'terms-help' => 'Notice — a line under the button; checkbox — the buyer ticks it before placing the order. The agreement goes into the order\'s history.',
        'terms-page' => 'Terms page',
        'terms-page-help' => 'A page of the site or an address the agreement links to.',
        'privacy-page' => 'Privacy policy',
        'privacy-page-help' => 'A second document, if the site has one: how personal data is processed.',
        'contact-needed' => 'Keep the phone or the e-mail required: an order needs one of them.',
        'values' => [
            'required' => 'Required',
            'optional' => 'Optional',
            'hidden' => 'Hidden',
            'on' => 'On',
            'off' => 'Off',
            'none' => 'None',
            'notice' => 'Notice',
            'checkbox' => 'Checkbox',
        ],
    ],
];
