<?php

declare(strict_types=1);

/*
 * Keys for programs: issued under Administrators → Keys, shown once, revoked there. The words
 * are about a key a program holds, not about tokens and scopes underneath — except the scopes
 * themselves, which are kept as issued because an agent's refusal quotes them.
 */

return [
    'title' => 'Klucze',
    'issue' => 'Wydaj klucz',
    'issue-short' => 'Wydaj',
    'lead' => 'Klucz pozwala programowi — strażnikowi kolejki, nocnemu importowi — pracować w tym panelu bez przeglądarki. Działa jako wybrany administrator i nigdy nie może więcej niż on. Klucz to program, a nie osoba: tego, co moduł zostawia osobie, na przykład etapu sprawdzanego ręcznie, zrobić nie może.',
    'name' => 'Nazwa',
    'name-hint' => 'Czym jest program, żeby lista to mówiła: „strażnik kolejki”, „nocny import”.',
    'who' => 'Działa jako',
    'who-hint' => 'Wszystko, co klucz zmienia, dzieje się w imieniu tego administratora i w granicach jego uprawnień.',
    'allowed' => 'Co wolno',
    'everything' => 'Wszystko, co może administrator',
    'chosen' => 'Tylko zakresy wybrane poniżej',
    'scopes' => 'Zakresy',
    'scopes-hint' => 'Moduł zainstalowany później pozostaje poza zasięgiem, dopóki nie nazwie go nowy klucz.',
    'scopes-required' => 'Wybierz co najmniej jeden zakres.',
    'lifetime' => 'Ważność',
    'lifetime-hint' => 'Klucza nie da się przedłużyć: wydaj nowy, zanim ten wygaśnie, i unieważnij stary.',
    'days-30' => '30 dni',
    'days-90' => '90 dni',
    'days-365' => '1 rok',
    'days-1095' => '3 lata',
    'days-3650' => '10 lat',
    'issued-title' => 'Skopiuj klucz teraz',
    'issued-text' => 'Klucz jest pokazywany tylko ten jeden raz. Nikt go już nie zobaczy — ani tutaj, ani na serwerze. Zgubiony unieważnij i wydaj nowy.',
    'secret' => 'Klucz',
    'copy' => 'Kopiuj',
    'copied' => 'Skopiowano',
    'copy-failed' => 'Brak dostępu do schowka. Zaznacz klucz i skopiuj go ręcznie.',
    'address' => 'Adres serwera',
    'header-hint' => 'Program wysyła klucz w nagłówku: Authorization: Bearer <klucz>.',
    'done' => 'Gotowe',
    'cancel' => 'Anuluj',
    'expires' => 'Wygasa',
    'until' => 'do',
    'last-used' => 'Ostatnio użyty',
    'never-used' => 'Nigdy',
    'issued-by' => 'Wydał :name',
    'issued-console' => 'Wydany w terminalu',
    'full' => 'Wszystko, co może :name',
    'deleted' => 'Administrator nr :id',
    'revoke' => 'Unieważnij',
    'revoked' => 'Unieważniony',
    'expired' => 'Wygasł',
    'confirm-title' => 'Unieważnić „:name”?',
    'confirm-text' => 'Program, który go używa, dostanie odmowę przy następnym wywołaniu. Unieważnionego klucza nie można włączyć ponownie.',
    'confirm' => 'Unieważnij',
    'revoked-done' => '„:name” unieważniony',
    'empty' => 'Nie wydano jeszcze żadnego klucza',
];
