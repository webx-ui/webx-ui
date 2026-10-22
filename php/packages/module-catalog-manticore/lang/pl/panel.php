<?php

declare(strict_types=1);

return [
    'title' => 'Indeks wyszukiwania',
    'lead' => 'Katalog wyszukuje, filtruje i liczy za pomocą Manticore. Gdy ten nie odpowiada, panel odpowiada z bazy danych.',
    'refresh' => 'Odśwież',
    'load-failed' => 'Nie udało się wczytać stanu indeksu.',

    'connection' => 'Serwer',
    'address' => 'Adres',
    'prefix' => 'Prefiks tabel',
    'version' => 'Wersja',
    'available' => 'Odpowiada',
    'unavailable' => 'Nie odpowiada',

    'tables' => 'Tabele',
    'language' => 'Język',
    'table' => 'Tabela',
    'in-index' => 'W indeksie',
    'in-database' => 'W bazie danych',
    'state' => 'Stan',
    'state-ready' => 'Aktualny',
    'state-stale' => 'Nieaktualny schemat',
    'state-missing' => 'Brak',
    'filling' => 'Przebudowa wypełnia tabelę obok.',
    'filled' => 'Przebudowa wypełnia tabelę obok: :done z :total',
    'no-tables' => 'Serwer nie podał, jakie ma tabele.',

    'queue' => 'Kolejka',
    'waiting' => 'Czeka na zapis',
    'oldest' => 'Najstarszy od',
    'queue-empty' => 'Każdy zapisany produkt jest w indeksie.',

    'outdated' => 'Indeks nie ma schematu, który katalog teraz zapisuje',
    'outdated-text' => 'Sklep odpowiada w tym czasie ze starych tabel, ale tego, czego w nich brakuje — nowego filtra, kodów — nie da się wyszukać. Przebudowa wypełnia nowe tabele obok działających i podmienia je; przy dużym katalogu to kilka minut obciążenia, więc wybierz porę.',
    'rebuild' => 'Przebuduj',
    'rebuild-again' => 'Przebuduj ponownie',
    'rebuild-title' => 'Przebudować indeks wyszukiwania?',
    'rebuild-text' => 'Każdy produkt zostanie zapisany od nowa do nowych tabel, które potem zastąpią działające. Sklep w tym czasie działa na starych tabelach.',
    'rebuild-busy' => 'Przebudowa już trwa.',
    'rebuild-locked' => 'Trwa inna przebudowa — uruchomiona z konsoli. Uruchom tę, gdy tamta się skończy.',
    'rebuild-console' => 'Trwa przebudowa uruchomiona z konsoli: przycisk wróci, gdy się skończy.',
    'rebuild-queued' => 'Czeka na obsługę kolejki',
    'rebuild-running' => 'Przebudowa: :done z :total',
    'rebuild-done' => 'Przebudowano produktów: :done',
    'rebuild-failed' => 'Przebudowa nie powiodła się',
    'rebuild-stalled' => 'Od kwadransa nie ma wieści od przebudowy: obsługa kolejki nie działa albo się zatrzymała. Uruchom przebudowę ponownie, gdy obsługa kolejki będzie działać.',
    'rebuild-started' => 'Przebudowa jest w kolejce.',
    'cancel' => 'Anuluj',
];
