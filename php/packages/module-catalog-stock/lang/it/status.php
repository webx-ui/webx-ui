<?php

declare(strict_types=1);

return [
    'title' => 'Nome',
    'code' => 'Codice',
    'code-help' => 'Lettere latine, cifre e trattini: lo stato nell’indirizzo del filtro (stock_in-stock) e nei template. Se vuoto, si ricava dal nome.',
    'color' => 'Tono',
    'color-help' => 'Il sito colora la riga dello stato in questo tono; il pannello mostra un tag dello stesso tono.',
    'tones' => [
        'neutral' => 'Neutro',
        'primary' => 'Primario',
        'success' => 'Successo',
        'warning' => 'Avviso',
        'danger' => 'Pericolo',
        'info' => 'Info',
    ],
    'purchasable' => 'Acquistabile',
    'purchasable-help' => 'Se disattivato, un prodotto in questo stato non si può acquistare, e il suo nome è ciò che il sito mostra al posto del pulsante di acquisto.',
    'default' => 'Predefinito',
    'default-help' => 'Lo stato di ogni prodotto a cui nessuno ha impostato uno stato, e di un prodotto nuovo. Per cambiarlo, rendi predefinito un altro stato.',
    'filterable' => 'Nel filtro',
    'filterable-help' => 'Se disattivato, lo stato non è una scelta del filtro del catalogo.',
    'new' => 'Nuovo stato',
    'empty' => 'Ancora nessuno stato di disponibilità.',
    'empty-help' => 'Uno stato dice se un prodotto è disponibile e se si può acquistare.',
    'order' => 'L’ordine qui è l’ordine del filtro',
    'hidden' => 'Non nel filtro',
    'count' => 'Prodotti: :count',
    'show-items' => 'Mostra i suoi prodotti',
    'delete-blocked' => 'Finché contiene prodotti non può essere eliminato: prima spostali in un altro stato.',
    'delete-text' => 'Va nel cestino ed esce dal filtro.',
    'deleted' => 'Lo stato è nel cestino.',
    'saved' => 'Lo stato è salvato.',
];
