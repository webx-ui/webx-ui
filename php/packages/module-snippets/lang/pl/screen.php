<?php

declare(strict_types=1);

return [
    'name' => 'Nazwa',
    'name-help' => 'Tylko dla administratorów, nigdy nie trafia na stronę.',
    'place' => 'Miejsce',
    'place-help' => 'Gdzie na stronie wyświetla się kod. Własne miejsca deklaruje się w config/webx-snippets.php.',
    'code' => 'Kod',
    'code-help' => 'Wklej dokładnie to, co dał serwis, bez <html>, <head> i <body>. document.write nie działa w kodzie czekającym na zgodę.',
    'settings' => 'Ustawienia',
    'enabled' => 'Włączony',
    'enabled-help' => 'Włączony kod jest na stronie zaraz po zapisaniu.',
    'consent' => 'Zgoda na cookie',
    'consent-help' => 'Wszystko poza „Niezbędnymi” wyświetla się dopiero po zgodzie odwiedzającego na tę kategorię.',
    'on-stage' => 'Także na dev i stage',
    'on-stage-help' => 'Wyłączone: kod wyświetla się tylko na produkcji, kopia strony nie liczy wizyt.',
    'coverage' => 'Gdzie się wyświetla',
    'only-paths' => 'Tylko na',
    'only-paths-help' => 'Ścieżki bez języka, * oznacza cokolwiek: /, /blog/*. Puste — cała strona.',
    'except-paths' => 'Oprócz',
    'except-paths-help' => 'Ścieżki, na których kod nigdy się nie wyświetla, np. /blog/drafts/*.',
    'locales' => 'Języki',
    'locales-help' => 'Nic nie zaznaczone — wszystkie języki.',
    'history' => 'Historia',
    'position' => 'Kolejność',
];
