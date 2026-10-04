<?php

declare(strict_types=1);

return [
    'empty' => 'L’adresse, la question et la réponse sont obligatoires.',
    'question-long' => 'La question dépasse 1000 caractères.',
    'foreign-host' => 'L’adresse est sur un autre site.',
    'duplicate' => 'Cette question figure déjà sur la page.',
    'redirected' => ':from redirige ; sa cible :to est utilisée à la place.',
    'unreadable' => 'Le fichier n’a pas pu être lu comme CSV ou XLSX.',
    'has-faq' => 'Cette règle a une FAQ, et seule une règle pour une adresse exacte peut la garder. Supprimez d’abord les questions.',
    'question-missing' => 'La réponse n’a pas de question.',
    'answer-missing' => 'La question n’a pas de réponse.',

    // The panel's view of it (§18.5).
    'tab' => 'FAQ',
    'meta' => 'Balises meta',
    'help' => 'Les questions de cette page. Elles vont dans son balisage FAQPage et sur la page là où le gabarit affiche la FAQ.',
    'exact-only' => 'Seule une règle pour une adresse exacte a une FAQ.',
    'question' => 'Question',
    'answer' => 'Réponse',
    'add' => 'Ajouter une question',
    'remove' => 'Retirer la question',
    'drag' => 'Déplacer',
    'none' => 'Pas encore de questions.',
    'column' => 'FAQ',
    'with-faq' => 'Seulement avec FAQ',
    'import' => 'Importer la FAQ',
    'export-csv' => 'Exporter la FAQ en CSV',
    'export-xlsx' => 'Exporter la FAQ en XLSX',
    'import-title' => 'Importer la FAQ',
    'import-help' => 'Un fichier CSV ou XLSX avec les colonnes adresse, question et réponse (HTML ou texte), dans la langue de l’adresse. Une adresse sans règle exacte en reçoit une aux balises meta vides.',
    'mode-replace' => 'Remplacer la FAQ des adresses du fichier',
    'mode-append' => 'Ajouter aux questions existantes',
    'imported' => 'Importé. Adresses : :count',
    'result-addresses' => 'Adresses',
    'result-questions' => 'Questions',
    'result-created' => 'Nouvelles règles',
    'result-replaced' => 'Remplacées',
    'result-appended' => 'Complétées',
    'result-errors' => 'Erreurs',
];
