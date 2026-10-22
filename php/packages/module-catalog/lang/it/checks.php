<?php

declare(strict_types=1);

return [
    'catalog' => [
        'category_description' => [
            'title' => 'Categorie senza descrizione',
            'found' => 'Alcune categorie pubblicate non hanno una descrizione.',
            'why' => 'Una pagina di categoria con solo un elenco di prodotti dice poco a un motore di ricerca e viene facilmente presa per una pagina povera di contenuti.',
            'fix' => 'Scrivi qualche frase su cosa contiene la categoria e su come scegliere al suo interno.',
        ],
        'product_image' => [
            'title' => 'Prodotti senza immagine',
            'found' => 'Alcuni prodotti pubblicati non hanno un’immagine.',
            'why' => 'Un prodotto senza immagine vende meno, sembra incompleto nell’elenco e non ha nulla da mostrare quando viene condiviso.',
            'fix' => 'Aggiungi almeno un’immagine a ogni prodotto, oppure ritira dal sito quelli non ancora pronti.',
        ],
        'product_price' => [
            'title' => 'Prodotti senza prezzo',
            'found' => 'Alcuni prodotti pubblicati non hanno un prezzo, o hanno prezzo zero.',
            'why' => 'Il visitatore non capisce quanto costa, e il prodotto non può comparire nei risultati di shopping.',
            'fix' => 'Imposta il prezzo, oppure ritira dal sito i prodotti non ancora in vendita.',
        ],
    ],
];
