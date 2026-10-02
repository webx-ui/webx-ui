<?php

declare(strict_types=1);

return [
    'config' => [
        'debug' => [
            'title' => 'Tryb debugowania na działającej domenie',
            'found' => 'APP_DEBUG=true na domenie, która nie jest środowiskiem deweloperskim.',
            'why' => 'Każda strona błędu pokazuje kod, zapytania i środowisko, w tym hasła, każdemu, kto na nią trafi.',
            'fix' => 'Ustaw APP_DEBUG=false w .env i uruchom php artisan config:cache.',
        ],
        'env' => [
            'title' => 'Środowisko nie jest production',
            'found' => 'APP_ENV nie jest production na działającej domenie.',
            'why' => 'Poza production pakiety zachowują się inaczej: cache, strony błędów, poczta i narzędzia debugowania.',
            'fix' => 'Ustaw APP_ENV=production w .env i uruchom php artisan config:cache.',
        ],
        'app_url' => [
            'title' => 'APP_URL nie pasuje do witryny',
            'found' => 'APP_URL różni się od schematu i hosta, pod którymi odpowiada witryna.',
            'why' => 'Każdy bezwzględny adres, który drukuje witryna — mapa witryny, linki canonical, wiadomości, linki do plików — wskazuje gdzie indziej.',
            'fix' => 'Ustaw APP_URL na adres, którego używają użytkownicy, z https, jeśli witryna go ma, i uruchom php artisan config:cache.',
        ],
        'queue' => [
            'title' => 'Kolejka działa wewnątrz żądania',
            'found' => 'Sterownik kolejki to sync.',
            'why' => 'Wiadomości i zgłoszenia są obsługiwane, gdy użytkownik czeka, wolny serwer poczty spowalnia formularze, a długich zadań, takich jak audyt, nie da się uruchomić z panelu.',
            'fix' => 'Użyj kolejki database lub redis i utrzymuj działający worker (php artisan queue:work pod supervisorem).',
        ],
        'mail' => [
            'title' => 'Poczta idzie donikąd',
            'found' => 'Mailer zapisuje wiadomości do logu lub do pamięci.',
            'why' => 'Każdy formularz mówi „wysłano”, a nikt nigdy nie dostaje wiadomości.',
            'fix' => 'Skonfiguruj prawdziwy mailer (SMTP lub API) w .env: MAIL_MAILER i jego ustawienia.',
        ],
        'schedule' => [
            'title' => 'Harmonogram nie działa',
            'found' => 'Harmonogram nie był uruchomiony od ponad godziny.',
            'why' => 'Kopie zapasowe, przycinanie dziennika i wszystko inne z harmonogramu po cichu przestaje działać.',
            'fix' => 'Dodaj „* * * * * php artisan schedule:run” do crontaba użytkownika witryny.',
        ],
        'storage_link' => [
            'title' => 'Brak linku public/storage',
            'found' => 'public/storage nie istnieje.',
            'why' => 'Każdy wgrany obraz i plik w witrynie odpowiada 404.',
            'fix' => 'Uruchom php artisan storage:link na serwerze.',
        ],
        'site_gate' => [
            'title' => 'Witryna jest zamknięta hasłem',
            'found' => 'Bramka witryny jest włączona.',
            'why' => 'Wyszukiwarki nic nie widzą za hasłem — dobrze, gdy witryna jest testowana, źle po starcie.',
            'fix' => 'Ustaw WEBX_SITE_GATE=false, gdy witryna zostanie otwarta.',
        ],
    ],
    'host' => [
        'mirror' => [
            'title' => 'Odpowiadają dwa lustra',
            'found' => 'Zarówno www, jak i nazwa bez niego odpowiadają 200.',
            'why' => 'Każda strona istnieje w dwóch kopiach, a wyszukiwarki dzielą jej wagę między nie.',
            'fix' => 'Przekieruj drugą nazwę na główną jednym przekierowaniem 301 w serwerze WWW.',
        ],
        'https' => [
            'title' => 'http nie prowadzi do https w jednym kroku',
            'found' => 'http:// odpowiada samo, prowadzi gdzie indziej albo dociera do https przez łańcuch.',
            'why' => 'Użytkownicy trafiają na niezabezpieczoną kopię, a każdy dodatkowy krok kosztuje czas i wagę linków.',
            'fix' => 'Jedno przekierowanie 301 z http:// na https:// głównego hosta, w serwerze WWW.',
        ],
        'tls' => [
            'title' => 'Problem z certyfikatem',
            'found' => 'Certyfikat wkrótce wygasa, dotyczy innego hosta albo nie jest zaufany.',
            'why' => 'Przeglądarki pokazują ostrzeżenie na całą stronę i większość użytkowników odchodzi.',
            'fix' => 'Odnów certyfikat (sprawdź, czy automatyczne odnawianie działa) i serwuj pełny łańcuch dla tego hosta.',
        ],
        'hsts' => [
            'title' => 'Brak HSTS',
            'found' => 'Brak nagłówka Strict-Transport-Security.',
            'why' => 'Pierwsza wizyta może nadal przejść przez zwykłe http i zostać przechwycona.',
            'fix' => 'Dodaj Strict-Transport-Security: max-age=31536000 w serwerze WWW, gdy https będzie stabilne.',
        ],
        'index_files' => [
            'title' => 'Pliki index odpowiadają',
            'found' => '/index.php lub inny plik index odpowiada 200.',
            'why' => 'Strona jest dostępna pod drugim adresem — duplikat dla wyszukiwarek.',
            'fix' => 'Przekieruj pliki index na adres bez nich przekierowaniem 301.',
        ],
        'slashes' => [
            'title' => 'Podwójne ukośniki nie są scalane',
            'found' => 'Adres z // odpowiada 200.',
            'why' => 'Każdy błędnie wpisany link tworzy kolejną kopię strony.',
            'fix' => 'Przekieruj adresy z powtórzonymi ukośnikami na scalony adres przekierowaniem 301.',
        ],
        'trailing_slash' => [
            'title' => 'Z ukośnikiem na końcu i bez niego',
            'found' => 'Ta sama strona odpowiada z ukośnikiem na końcu i bez niego.',
            'why' => 'Dwa adresy jednej strony dzielą jej wagę.',
            'fix' => 'Wybierz jedną formę i przekieruj drugą przekierowaniem 301.',
        ],
        'case' => [
            'title' => 'Wielkość liter nie jest normalizowana',
            'found' => 'Adres z wielkimi literami odpowiada 200.',
            'why' => 'Link wpisany inną wielkością liter tworzy duplikat.',
            'fix' => 'Przekieruj adresy z wielkimi literami na adres z małymi przekierowaniem 301.',
        ],
        'soft_404' => [
            'title' => 'Brakujące strony nie odpowiadają 404',
            'found' => 'Adres, który nie może istnieć, odpowiada 200 lub przekierowuje.',
            'why' => 'Wyszukiwarki indeksują literówki i usunięte strony jako prawdziwe.',
            'fix' => 'Odpowiadaj 404 dla nieznanych adresów; nie przekierowuj ich na stronę główną.',
        ],
        '404_page' => [
            'title' => 'Strona 404 donikąd nie prowadzi',
            'found' => 'Strona 404 nie zawiera linku do strony głównej.',
            'why' => 'Użytkownik, który kliknął zepsuty link, nie ma dokąd pójść.',
            'fix' => 'Dodaj do szablonu 404 link do strony głównej, wyszukiwarki lub głównych działów.',
        ],
        'compression' => [
            'title' => 'HTML bez kompresji',
            'found' => 'Strony są wysyłane bez gzip lub brotli.',
            'why' => 'Strony ważą kilkakrotnie więcej i otwierają się wolniej, zwłaszcza na urządzeniach mobilnych.',
            'fix' => 'Włącz gzip lub brotli dla text/html w serwerze WWW.',
        ],
        'security_headers' => [
            'title' => 'Brakuje nagłówków bezpieczeństwa',
            'found' => 'Brakuje niektórych z X-Content-Type-Options, Referrer-Policy i ochrony przed osadzaniem w ramkach.',
            'why' => 'Zamykają tanie ataki: MIME sniffing, wyciek adresów, clickjacking.',
            'fix' => 'Dodaj nagłówki w serwerze WWW: nosniff, strict-origin-when-cross-origin, SAMEORIGIN.',
        ],
        'server_leak' => [
            'title' => 'Serwer ujawnia swoje wersje',
            'found' => 'X-Powered-By lub Server z numerem wersji.',
            'why' => 'Gotowa mapa dla każdego, kto szuka znanej luki w tej wersji.',
            'fix' => 'Wyłącz expose_php i server_tokens (lub ich odpowiedniki).',
        ],
        'static_cache' => [
            'title' => 'Pliki statyczne nie są cache’owane',
            'found' => 'CSS, JS lub obrazy bez Cache-Control albo z cache krótszym niż tydzień.',
            'why' => 'Każda strona pobiera je ponownie.',
            'fix' => 'Nadaj wersjonowanym plikom statycznym długi Cache-Control (rok, immutable) w serwerze WWW.',
        ],
    ],
    'hosts' => [
        'dev_content' => [
            'title' => 'Linki do środowiska deweloperskiego w treści',
            'found' => 'Adres środowiska deweloperskiego we wpisie — opublikowanym, w wersji roboczej lub w polu, którego szablon nie drukuje.',
            'why' => 'Treść wypełniona w środowisku deweloperskim trafia na produkcję z linkami i obrazami wskazującymi z powrotem na to środowisko; użytkownicy dostają błędy, a środowisko zostaje zaindeksowane.',
            'fix' => 'Otwórz wpis i zamień adres środowiska deweloperskiego na adres samej witryny lub link względny. Wypisz środowiska w ustawieniach audytu, aby wszystkie zostały wychwycone.',
        ],
    ],
];
