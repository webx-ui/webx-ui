<?php

declare(strict_types=1);

return [
    'empty' => 'Indirizzo, domanda e risposta sono obbligatori.',
    'question-long' => 'La domanda supera i 1000 caratteri.',
    'foreign-host' => 'L’indirizzo è su un altro sito.',
    'duplicate' => 'Questa domanda è già nella pagina.',
    'redirected' => ':from reindirizza; al suo posto si usa la destinazione :to.',
    'unreadable' => 'Impossibile leggere il file come CSV o XLSX.',
    'has-faq' => 'Questa regola ha una FAQ, e solo una regola per un indirizzo esatto può tenerla. Rimuovi prima le domande.',
    'question-missing' => 'La risposta non ha una domanda.',
    'answer-missing' => 'La domanda non ha una risposta.',

    // The panel's view of it (§18.5).
    'tab' => 'FAQ',
    'meta' => 'Meta tag',
    'help' => 'Le domande di questa pagina. Finiscono nel suo markup FAQPage e nella pagina dove il template stampa la FAQ.',
    'exact-only' => 'Solo una regola per un indirizzo esatto ha una FAQ.',
    'question' => 'Domanda',
    'answer' => 'Risposta',
    'add' => 'Aggiungi una domanda',
    'remove' => 'Rimuovi la domanda',
    'drag' => 'Sposta',
    'none' => 'Ancora nessuna domanda.',
    'column' => 'FAQ',
    'with-faq' => 'Solo con FAQ',
    'import' => 'Importa FAQ',
    'export-csv' => 'Esporta FAQ in CSV',
    'export-xlsx' => 'Esporta FAQ in XLSX',
    'import-title' => 'Importa FAQ',
    'import-help' => 'Un file CSV o XLSX con le colonne indirizzo, domanda e risposta (HTML o testo), nella lingua dell’indirizzo. Un indirizzo senza regola esatta ne riceve una con i meta tag vuoti.',
    'mode-replace' => 'Sostituisci la FAQ degli indirizzi del file',
    'mode-append' => 'Aggiungi alle domande esistenti',
    'imported' => 'Importato. Indirizzi: :count',
    'result-addresses' => 'Indirizzi',
    'result-questions' => 'Domande',
    'result-created' => 'Nuove regole',
    'result-replaced' => 'Sostituite',
    'result-appended' => 'Integrate',
    'result-errors' => 'Errori',
];
