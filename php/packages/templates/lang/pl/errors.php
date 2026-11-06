<?php

declare(strict_types=1);

return [
    'template-unclosed' => 'Sekcja {{#:section}} nie jest zamknięta: zakończ ją tagiem {{/:section}}.',
    'template-unexpected' => '{{/:section}} zamyka sekcję, która nie została otwarta.',
    'template-tag' => 'Tag {{:tag nie jest zamknięty: zakończ go przez }}.',
    'template-name' => '{{:name}} to nie nazwa zmiennej: małe litery, cyfry i _, najwyżej jedna kropka.',
    'template-partial' => 'Szablony częściowe ({{>name}}) nie są dostępne w szablonie wiadomości.',
    'template-delimiters' => 'Zmiana ograniczników ({{=<% %>=}}) nie jest dostępna w szablonie wiadomości.',
    'template-pragma' => 'Pragmy i bloki ({{%…}}, {{$…}}) nie są dostępne w szablonie wiadomości.',
    'template-cdata' => 'Zmienna nie może stać wewnątrz literału CDATA: {{:name}} zostałaby zakodowana dwukrotnie. Usuń otaczające ją CDATA i użyj zmiennej z _cdata, np. {{description_cdata}}.',
];
