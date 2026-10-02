<?php

declare(strict_types=1);

return [
    'config' => [
        'debug' => [
            'title' => 'Debug-Modus auf einer Live-Domain',
            'found' => 'APP_DEBUG=true auf einer Domain, die keine Entwicklungsumgebung ist.',
            'why' => 'Jede Fehlerseite zeigt jedem, der auf eine stößt, den Code, die Abfragen und die Umgebung, einschließlich Passwörtern.',
            'fix' => 'Setzen Sie APP_DEBUG=false in .env und führen Sie php artisan config:cache aus.',
        ],
        'env' => [
            'title' => 'Die Umgebung ist nicht production',
            'found' => 'APP_ENV ist auf einer Live-Domain nicht production.',
            'why' => 'Außerhalb von production verhalten sich Pakete anders: Caches, Fehlerseiten, E-Mail und Debugging-Werkzeuge.',
            'fix' => 'Setzen Sie APP_ENV=production in .env und führen Sie php artisan config:cache aus.',
        ],
        'app_url' => [
            'title' => 'APP_URL passt nicht zur Website',
            'found' => 'APP_URL weicht von Schema und Host ab, unter denen die Website antwortet.',
            'why' => 'Jede absolute Adresse, die die Website ausgibt — Sitemap, Canonical-Links, E-Mails, Dateilinks — zeigt woandershin.',
            'fix' => 'Setzen Sie APP_URL auf die Adresse, die Besucher verwenden, mit https, falls vorhanden, und führen Sie php artisan config:cache aus.',
        ],
        'queue' => [
            'title' => 'Die Queue läuft innerhalb der Anfrage',
            'found' => 'Der Queue-Treiber ist sync.',
            'why' => 'E-Mails und Einsendungen werden bearbeitet, während der Besucher wartet, ein langsamer Mailserver macht Formulare langsam, und lange Jobs wie das Audit lassen sich nicht aus dem Panel starten.',
            'fix' => 'Verwenden Sie die Queue database oder redis und lassen Sie einen Worker laufen (php artisan queue:work unter einem Supervisor).',
        ],
        'mail' => [
            'title' => 'E-Mails gehen ins Leere',
            'found' => 'Der Mailer schreibt E-Mails ins Log oder in den Speicher.',
            'why' => 'Jedes Formular meldet „gesendet“, aber niemand erhält je eine E-Mail.',
            'fix' => 'Richten Sie in .env einen echten Mailer ein (SMTP oder eine API): MAIL_MAILER und seine Einstellungen.',
        ],
        'schedule' => [
            'title' => 'Der Scheduler läuft nicht',
            'found' => 'Der Scheduler ist seit mehr als einer Stunde nicht gelaufen.',
            'why' => 'Backups, das Kürzen des Journals und alles andere im Zeitplan bleibt unbemerkt stehen.',
            'fix' => 'Tragen Sie „* * * * * php artisan schedule:run“ in die Crontab des Website-Benutzers ein.',
        ],
        'storage_link' => [
            'title' => 'Kein public/storage-Link',
            'found' => 'public/storage existiert nicht.',
            'why' => 'Jedes hochgeladene Bild und jede Datei der Website antwortet mit 404.',
            'fix' => 'Führen Sie php artisan storage:link auf dem Server aus.',
        ],
        'site_gate' => [
            'title' => 'Die Website ist mit einem Passwort geschützt',
            'found' => 'Der Website-Schutz ist eingeschaltet.',
            'why' => 'Suchmaschinen sehen hinter dem Passwort nichts — richtig, solange die Website getestet wird, falsch nach dem Start.',
            'fix' => 'Setzen Sie WEBX_SITE_GATE=false, wenn die Website online geht.',
        ],
    ],
    'host' => [
        'mirror' => [
            'title' => 'Zwei Spiegel antworten',
            'found' => 'Sowohl www als auch der Name ohne www antworten mit 200.',
            'why' => 'Jede Seite existiert doppelt, und Suchmaschinen verteilen ihr Gewicht auf beide Kopien.',
            'fix' => 'Leiten Sie den zweiten Namen im Webserver mit einer einzigen 301-Weiterleitung auf den Hauptnamen um.',
        ],
        'https' => [
            'title' => 'http führt nicht in einem Schritt zu https',
            'found' => 'http:// antwortet selbst, führt woandershin oder erreicht https über eine Kette.',
            'why' => 'Besucher landen auf einer unsicheren Kopie, und jeder zusätzliche Schritt kostet Zeit und Link-Gewicht.',
            'fix' => 'Eine 301-Weiterleitung von http:// auf https:// des Haupt-Hosts, im Webserver.',
        ],
        'tls' => [
            'title' => 'Zertifikatsproblem',
            'found' => 'Das Zertifikat läuft bald ab, nennt einen anderen Host oder ist nicht vertrauenswürdig.',
            'why' => 'Browser zeigen eine ganzseitige Warnung, und die meisten Besucher verlassen die Seite.',
            'fix' => 'Erneuern Sie das Zertifikat (prüfen Sie, ob die automatische Erneuerung funktioniert) und liefern Sie die vollständige Kette für diesen Host aus.',
        ],
        'hsts' => [
            'title' => 'Kein HSTS',
            'found' => 'Kein Strict-Transport-Security-Header.',
            'why' => 'Der erste Besuch kann noch über unverschlüsseltes http laufen und abgefangen werden.',
            'fix' => 'Fügen Sie Strict-Transport-Security: max-age=31536000 im Webserver hinzu, sobald https stabil läuft.',
        ],
        'index_files' => [
            'title' => 'Index-Dateien antworten',
            'found' => '/index.php oder eine andere Index-Datei antwortet mit 200.',
            'why' => 'Die Seite ist unter einer zweiten Adresse erreichbar — ein Duplikat für Suchmaschinen.',
            'fix' => 'Leiten Sie Index-Dateien mit einer 301-Weiterleitung auf die Adresse ohne sie um.',
        ],
        'slashes' => [
            'title' => 'Doppelte Schrägstriche werden nicht zusammengefasst',
            'found' => 'Eine Adresse mit // antwortet mit 200.',
            'why' => 'Jeder Tippfehler in einem Link erzeugt eine weitere Kopie der Seite.',
            'fix' => 'Leiten Sie Adressen mit wiederholten Schrägstrichen mit einer 301-Weiterleitung auf die zusammengefasste um.',
        ],
        'trailing_slash' => [
            'title' => 'Mit und ohne abschließenden Schrägstrich',
            'found' => 'Dieselbe Seite antwortet mit und ohne abschließenden Schrägstrich.',
            'why' => 'Zwei Adressen für eine Seite teilen ihr Gewicht auf.',
            'fix' => 'Wählen Sie eine Form und leiten Sie die andere mit einer 301-Weiterleitung um.',
        ],
        'case' => [
            'title' => 'Groß- und Kleinschreibung wird nicht vereinheitlicht',
            'found' => 'Eine Adresse mit Großbuchstaben antwortet mit 200.',
            'why' => 'Ein in anderer Schreibweise eingegebener Link erzeugt ein Duplikat.',
            'fix' => 'Leiten Sie Adressen mit Großbuchstaben mit einer 301-Weiterleitung auf die Kleinschreibung um.',
        ],
        'soft_404' => [
            'title' => 'Fehlende Seiten antworten nicht mit 404',
            'found' => 'Eine Adresse, die nicht existieren kann, antwortet mit 200 oder leitet weiter.',
            'why' => 'Suchmaschinen indexieren Tippfehler und gelöschte Seiten als echte Seiten.',
            'fix' => 'Antworten Sie bei unbekannten Adressen mit 404; leiten Sie sie nicht auf die Startseite um.',
        ],
        '404_page' => [
            'title' => 'Die 404-Seite führt nirgendwohin',
            'found' => 'Die 404-Seite enthält keinen Link zur Startseite.',
            'why' => 'Ein Besucher, der einem defekten Link gefolgt ist, kann nirgendwohin weiter.',
            'fix' => 'Fügen Sie dem 404-Template einen Link zur Startseite, zur Suche oder zu den Hauptbereichen hinzu.',
        ],
        'compression' => [
            'title' => 'HTML ohne Kompression',
            'found' => 'Seiten werden ohne gzip oder brotli gesendet.',
            'why' => 'Seiten sind um ein Vielfaches größer und laden langsamer, besonders mobil.',
            'fix' => 'Schalten Sie gzip oder brotli für text/html im Webserver ein.',
        ],
        'security_headers' => [
            'title' => 'Sicherheits-Header fehlen',
            'found' => 'Einige von X-Content-Type-Options, Referrer-Policy und dem Schutz vor Einbettung in Frames fehlen.',
            'why' => 'Sie schließen einfache Angriffe aus: MIME-Sniffing, Preisgabe von Adressen, Clickjacking.',
            'fix' => 'Fügen Sie die Header im Webserver hinzu: nosniff, strict-origin-when-cross-origin, SAMEORIGIN.',
        ],
        'server_leak' => [
            'title' => 'Der Server verrät seine Versionen',
            'found' => 'X-Powered-By oder Server mit einer Versionsnummer.',
            'why' => 'Eine fertige Landkarte für alle, die nach einer bekannten Schwachstelle in dieser Version suchen.',
            'fix' => 'Schalten Sie expose_php und server_tokens aus (oder deren Entsprechungen).',
        ],
        'static_cache' => [
            'title' => 'Statische Dateien werden nicht gecacht',
            'found' => 'CSS, JS oder Bilder ohne Cache-Control oder mit weniger als einer Woche Cache.',
            'why' => 'Jede Seite lädt sie erneut herunter.',
            'fix' => 'Geben Sie versionierten statischen Dateien im Webserver ein langes Cache-Control (ein Jahr, immutable).',
        ],
    ],
    'hosts' => [
        'dev_content' => [
            'title' => 'Links auf eine Entwicklungsumgebung im Inhalt',
            'found' => 'Eine Adresse einer Entwicklungsumgebung in einem Eintrag — veröffentlicht, im Entwurf oder in einem Feld, das das Template nicht ausgibt.',
            'why' => 'Auf einer Entwicklungsumgebung erfasste Inhalte gehen live, mit Links und Bildern, die zurück auf die Entwicklungsumgebung zeigen; Besucher erhalten Fehler, und die Entwicklungsumgebung wird indexiert.',
            'fix' => 'Öffnen Sie den Eintrag und ersetzen Sie die Adresse der Entwicklungsumgebung durch die der Website selbst oder durch einen relativen Link. Tragen Sie die Entwicklungsumgebungen in den Audit-Einstellungen ein, damit alle erfasst werden.',
        ],
    ],
];
