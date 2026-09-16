<?php

declare(strict_types=1);

return [
    'home-exists' => 'This site already has a home page; a second root is not possible.',
    'home-immovable' => 'The home page cannot be moved.',
    'home-undeletable' => 'The home page cannot be deleted.',
    'home-address' => 'The home page has no address of its own: its slug stays empty.',
    'home-missing' => 'This site has no home page, so a page has nowhere to go. Run the migrations.',
    'home-no-siblings' => 'The home page has no neighbours; a page can only go inside it.',
    'move-into-self' => 'A page cannot be moved inside itself or inside one of its own pages.',
    'parent-trashed' => 'That page is in the bin. Restore it before putting anything inside it.',
    'slug-shape' => 'An address may hold letters, digits, hyphens and underscores.',

    // The editor.
    'conflict' => ':name changed this page while you were editing it.',
    'conflict-anonymous' => 'This page changed while you were editing it.',
];
