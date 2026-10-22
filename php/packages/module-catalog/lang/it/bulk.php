<?php

declare(strict_types=1);

return [
    'actions' => [
        'publish' => 'Pubblica',
        'unpublish' => 'Ritira dal sito',
        'set-category' => 'Imposta la categoria principale',
        'add-category' => 'Aggiungi una categoria',
        'remove-category' => 'Rimuovi una categoria',
        'delete' => 'Elimina',
        'restore' => 'Ripristina',
    ],
    'params' => [
        'category' => 'Categoria',
    ],
    'errors' => [
        'selection' => 'Scegli i prodotti: un elenco di ID o la query dell’elenco.',
        'too-many' => 'Troppi ID in una volta; invia invece la query dell’elenco.',
        'unknown-action' => 'Questa azione di massa non esiste. Note: :known',
        'forbidden' => 'Questa azione richiede il permesso :permission.',
        'gone' => 'Il prodotto non è più dove l’esecuzione lo aveva trovato.',
    ],
];
