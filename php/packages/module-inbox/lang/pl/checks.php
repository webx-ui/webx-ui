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
    ],
];
