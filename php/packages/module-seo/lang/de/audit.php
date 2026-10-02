<?php

declare(strict_types=1);

return [
    'on' => 'Ein',
    'normalise-note' => 'Funktioniert für Anfragen, die der Webserver an die Website übergibt. Bleibt der Befund nach dem nächsten Lauf bestehen, beantwortet der Webserver diese Adresse selbst — richten Sie die Weiterleitung dort ein.',
    'robots-file-note' => 'Die Website hat eine Datei public/robots.txt. Der Webserver liefert sie aus, bevor die Website gefragt wird, löschen Sie sie also, damit die Einstellung wirkt.',
    'redirect-chain' => 'Sprünge vor der Seite: :steps',
    'title-duplicate' => '„:title“ — in :count Karten',
    'redirect-broken' => ':target antwortet :status',
    'rule-dead' => 'Die Adresse antwortet :status',
];
