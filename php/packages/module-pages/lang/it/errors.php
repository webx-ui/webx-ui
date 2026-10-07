<?php

declare(strict_types=1);

return [
    'home-exists' => 'Questo sito ha già una home page; una seconda radice non è possibile.',
    'home-immovable' => 'La home page non può essere spostata.',
    'home-undeletable' => 'La home page non può essere eliminata.',
    'home-address' => 'La home page non ha un indirizzo proprio: il suo slug resta vuoto.',
    'home-missing' => 'Questo sito non ha una home page, quindi una pagina nuova non ha dove stare. Esegui le migrazioni.',
    'home-no-siblings' => 'La home page non ha vicine; una pagina può andare soltanto al suo interno.',
    'move-into-self' => 'Una pagina non può essere spostata dentro sé stessa né dentro le proprie pagine.',
    'parent-trashed' => 'Quella pagina è nel cestino. Ripristinala prima di metterci qualcosa dentro.',
    'not-in-bin' => 'Solo una pagina nel cestino può essere eliminata per sempre. Eliminatela prima.',
    'slug-shape' => 'Un indirizzo accetta lettere, cifre, trattini e trattini bassi.',
    'ancestor-trashed' => 'La pagina sopra, «:title» (#:id), è nel cestino. Ripristina prima quella.',
    'move-beside-self' => 'Una pagina non può stare prima o dopo sé stessa.',
    'title-required' => 'Il titolo non può essere vuoto nella lingua principale del sito (:locale).',

    // The editor.
    'conflict' => ':name ha modificato questa pagina mentre la modificavi.',
    'conflict-anonymous' => 'Questa pagina è cambiata mentre la modificavi.',
];
