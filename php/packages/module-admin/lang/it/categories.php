<?php

declare(strict_types=1);

/*
 * The words of the categories every module shares (`WebxUi\Admin\Categories`). A module that
 * calls its categories something else — rubrics — says its own refusal instead.
 */

return [
    'in-use' => 'Voci al suo interno: :count. Spostale prima in un’altra categoria.',
    'slug-shape' => 'Lettere, cifre e singoli trattini tra di esse.',

    // The shared screens of categories (`categories/` in @webx-ui/module-admin). A module
    // that calls its categories something else hands its own keys in over these.
    'new' => 'Nuova categoria',
    'empty' => 'Ancora nessuna categoria.',
    'empty-help' => 'Una categoria raggruppa delle voci. Una voce può stare in più categorie.',
    'order' => 'L’ordine qui è l’ordine sul sito',
    'hidden' => 'Nascosta sul sito',
    'no-address' => 'Nessun indirizzo in questa lingua',
    'count' => 'Voci: :count',
    'show-items' => 'Mostra le sue voci',
    'edit' => 'Modifica',
    'open-on-site' => 'Apri sul sito',
    'delete' => 'Elimina',
    'delete-blocked' => 'Finché contiene voci non si può eliminare: spostale prima.',
    'delete-title' => 'Eliminare «:name»?',
    'delete-text' => 'Finisce nel cestino e sparisce dal sito, e il suo indirizzo torna libero.',
    'deleted' => 'La categoria è nel cestino.',
    'cancel' => 'Annulla',
    'create' => 'Crea',
    'save' => 'Salva',
    'saved' => 'Salvato.',
    'save-failed' => 'Non salvato: guarda i campi segnalati.',
    'reorder-failed' => 'Il nuovo ordine non è stato salvato.',
    'field-title' => 'Nome',
    'field-slug' => 'Indirizzo',
    'address-moving' => 'L’indirizzo cambia. Quello vecchio continua a funzionare e porta al nuovo.',
    'untitled' => 'Senza titolo',
    'trail' => 'Dove sei',
    'leave-title' => 'Uscire senza salvare?',
    'leave-text' => 'Ciò che è stato cambiato qui dall’ultimo salvataggio andrà perso.',
    'leave' => 'Esci',
    'field-main' => 'Principale',
    'field-add' => 'Aggiungi una categoria',
    'field-remove' => 'Togli da questa categoria',
    'field-empty' => 'Ancora in nessuna categoria.',
    'field-none-left' => 'Tutte le categorie sono già scelte.',
    'order-all' => 'Trascina per cambiare l’ordine sul sito.',
    'order-category' => 'Trascina per cambiare l’ordine dentro questa categoria. Il resto dell’elenco mantiene il suo.',
    'order-locked' => 'Svuota la ricerca e i filtri per cambiare l’ordine: si trascina solo l’elenco intero o una categoria.',
    'unknown' => 'Una delle categorie scelte non esiste più.',
];
