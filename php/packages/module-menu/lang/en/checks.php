<?php

declare(strict_types=1);

return [
    'menu' => [
        'broken' => [
            'title' => 'Menu items that lead to an error',
            'found' => 'A menu item leads to a page that answers with an error, or to a record that no longer has an address.',
            'why' => 'A menu is on every page: one broken item is a broken link everywhere, and the first thing a visitor clicks.',
            'fix' => 'Point the item at a page that exists, or remove it.',
        ],
        'redirect' => [
            'title' => 'Menu items that lead to a redirect',
            'found' => 'A menu item leads to an address that redirects elsewhere.',
            'why' => 'Every click costs an extra round trip, on every page the menu is printed on.',
            'fix' => 'Point the item at the address it ends up at.',
        ],
    ],
];
