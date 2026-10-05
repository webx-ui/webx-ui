<?php

declare(strict_types=1);

return [
    'inbox' => [
        'no_recipients' => [
            'title' => 'Formularze, które nikogo nie powiadamiają',
            'found' => 'Włączony formularz nie wskazuje żadnego odbiorcy, do którego dotarłby e-mail: nie ma żadnego, są tylko administratorzy usunięci lub wyłączeni od tamtej pory albo adresy, które nie są adresami.',
            'why' => 'Każde zgłoszenie jest zapisywane, a odwiedzający dostaje podziękowanie, ale nikt się o nim nie dowie, dopóki ktoś nie otworzy skrzynki — zapytanie może czekać całymi dniami.',
            'fix' => 'Otwórz formularz, przejdź na kartę Powiadomienia i dodaj administratora lub adres. Jeśli formularz czyta się tylko w panelu, zignoruj to zgłoszenie.',
        ],
        'notification' => [
            'title' => 'Powiadomienia o zgłoszeniach, które nie wyszły',
            'found' => 'Listy o zgłoszeniach nie zostały wysłane albo długo czekają w kolejce.',
            'why' => 'Osoby wskazane w formularzu nie wiedzą o zapytaniach, które panel już ma, a strona o tym milczy.',
            'fix' => 'Nieudane: popraw ustawienia poczty, uruchom ponownie workera kolejki (php artisan queue:restart), aby je wczytał, i wyślij powiadomienie ponownie ze zgłoszenia. Czekające: uruchom workera kolejki.',
        ],
    ],
];
