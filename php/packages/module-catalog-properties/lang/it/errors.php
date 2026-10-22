<?php

declare(strict_types=1);

return [
    'type' => 'Una proprietà è una di: :types.',
    'type-fixed' => 'Il tipo di una proprietà non cambia dopo il salvataggio: crea una nuova proprietà e sposta i valori.',
    'code' => 'Lettere latine, cifre e trattini singoli, al massimo :max caratteri.',
    'code-taken' => 'Questo codice in questa lingua è già usato da: :holder.',
    'slug' => 'Lettere latine, cifre e trattini singoli.',
    'slug-taken' => 'Un altro valore di questa proprietà ha questo slug in questa lingua.',
    'color' => 'Un colore è #rrggbb.',
    'group' => 'Questo gruppo non esiste.',
    'group-in-use' => 'Il gruppo contiene proprietà. Proprietà: :count',
    'unknown-property' => 'Questa proprietà non esiste.',
    'inherited' => '«:property» è già nell’insieme di «:category» più in alto; una categoria la eredita.',
    'outside-set' => 'Questa proprietà non è nell’insieme della categoria principale del prodotto.',
    'number' => 'Serve un numero.',
    'text' => 'Serve un testo, o una mappa di lingue.',
    'one-value' => 'Questa proprietà accetta un solo valore.',
    'unknown-value' => 'Questo valore non esiste per questa proprietà.',
    'unknown-slug' => '«:property» non ha il valore «:slug». Crealo prima con catalog_property_values_create, oppure indica l’id.',
    'leaves-only' => 'Qui si può scegliere solo un valore senza nulla sotto.',
    'value-in-use' => 'Alcuni prodotti hanno questo valore — uniscilo invece a un altro. Prodotti: :count',
    'values-select' => 'Solo un dizionario ha valori.',
    'not-tree' => 'I valori di questa proprietà non sono un albero.',
    'move-into-itself' => 'Un valore non può andare sotto sé stesso.',
    'merge-other' => 'Un valore si unisce a un altro valore della stessa proprietà.',
    'merge-descendant' => 'Un valore non può unirsi a uno che sta sotto di lui.',
    'intervals-number' => 'Solo un numero ha intervalli.',
    'interval-ends' => 'L’estremo superiore deve essere maggiore di quello inferiore.',
];
