<?php

declare(strict_types=1);

return [
    'name' => 'Nome',
    'name-help' => 'Solo per gli amministratori, non compare mai sul sito.',
    'place' => 'Posizione',
    'place-help' => 'Dove viene stampato il codice nella pagina. Le posizioni proprie si dichiarano in config/webx-snippets.php.',
    'code' => 'Codice',
    'code-help' => 'Incolla esattamente ciò che ha fornito il servizio, senza <html>, <head> e <body>. document.write non funziona in un codice che attende il consenso.',
    'settings' => 'Impostazioni',
    'enabled' => 'Attivo',
    'enabled-help' => 'Un codice attivo è sul sito appena salvato.',
    'consent' => 'Consenso ai cookie',
    'consent-help' => 'Tutto tranne «Necessari» viene stampato solo dopo che il visitatore accetta quella categoria.',
    'on-stage' => 'Anche su dev e stage',
    'on-stage-help' => 'Disattivato: il codice viene stampato solo in produzione, una copia del sito non conta visite.',
    'coverage' => 'Dove viene stampato',
    'only-paths' => 'Solo su',
    'only-paths-help' => 'Percorsi senza la lingua, * per qualsiasi cosa: /, /blog/*. Vuoto è tutto il sito.',
    'except-paths' => 'Tranne',
    'except-paths-help' => 'Percorsi dove il codice non viene mai stampato, come /blog/drafts/*.',
    'locales' => 'Lingue',
    'locales-help' => 'Nessuna selezione significa tutte le lingue.',
    'history' => 'Cronologia',
    'position' => 'Ordine',
];
