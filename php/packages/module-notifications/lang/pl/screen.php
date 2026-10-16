<?php

declare(strict_types=1);

return [
    'name' => 'Nazwa',
    'name-help' => 'Tylko dla administratorów.',
    'provider' => 'Dostawca',
    'provider-help' => 'Wybierany raz: kanał innego rodzaju to nowy kanał.',
    'event' => 'Dla zdarzenia',
    'event-help' => 'Ze zdarzeniem edytor podpowiada i sprawdza jego zmienne, a kanał jest proponowany tylko temu zdarzeniu. Bez zdarzenia kanał obsługuje każde zdarzenie.',
    'any-event' => 'Dowolne zdarzenie',
    'enabled' => 'Włączony',
    'enabled-help' => 'Wyłączony kanał nie wysyła niczego poza testami.',
    'connection' => 'Połączenie',
    'message' => 'Wiadomość',
    'subject' => 'Temat',
    'subject-help' => 'Jeden wiersz; zmienne takie jak {{form}} są podstawiane.',
    'template' => 'Szablon',
    'template-help' => 'Zmienne w podwójnych nawiasach klamrowych: {{form}}. Wystarczy jeden język; wiadomość w języku, którego brak w szablonie, używa głównego języka witryny.',
    'history' => 'Historia',
    'token-help' => 'Od @BotFather. Przechowywany w postaci zaszyfrowanej i nigdy więcej nie pokazywany: wpisz nowy, aby go zastąpić.',
    'chats' => 'Czaty',
    'chats-help' => 'Identyfikatory czatów lub „Znajdź czaty” po napisaniu do bota. Odbiorca ze zdarzenia to adres, który przynosi zdarzenie.',
    'mode-help' => 'Poczta witryny to ta skonfigurowana dla całej witryny.',
    'from-help' => 'Puste — nadawca witryny.',
    'emails' => 'Odbiorcy',
    'emails-help' => 'Administratorzy, adresy lub adres ze zdarzenia — odwiedzający, który wypełnił formularz.',
    'credentials' => 'Sekrety',
    'options' => 'Ustawienia',
    'recipients' => 'Odbiorcy',
];
