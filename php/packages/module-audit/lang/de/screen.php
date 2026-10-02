<?php

declare(strict_types=1);

return [
    'tab' => 'Audit',
    'base-url' => 'Zu prüfende Adresse',
    'base-url-help' => 'Leer bedeutet APP_URL. Das Audit ruft die Seiten der Website unter dieser Adresse ab.',
    'resolve-to' => 'Verbinden mit',
    'resolve-to-help' => 'Eine IP-Adresse oder ein Host, zu dem die Verbindung aufgebaut wird; der öffentliche Name bleibt in der Anfrage erhalten. Leer bedeutet die Antwort des DNS. Für Docker und Server hinter NAT.',
    'other-hosts' => 'Weitere Adressen dieser Website',
    'other-hosts-help' => 'Entwicklungs- und Staging-Umgebungen sowie alte Domains, eine pro Zeile. Ein Link auf eine davon ist ein Fehler, wo auch immer er gefunden wird.',
    'fix-unavailable' => 'Diese Korrektur kann diesen Befund nicht mehr beheben. Führen Sie das Audit erneut aus.',
];
