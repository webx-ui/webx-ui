<?php

declare(strict_types=1);

/*
 * The words of the categories every module shares (`WebxUi\Admin\Categories`). A module that
 * calls its categories something else — rubrics — says its own refusal instead.
 */

return [
    'in-use' => 'Wpisów w niej: :count. Najpierw przenieś je do innej kategorii.',
    'slug-shape' => 'Litery, cyfry i pojedyncze łączniki między nimi.',

    // The shared screens of categories (`categories/` in @webx-ui/module-admin). A module
    // that calls its categories something else hands its own keys in over these.
    'new' => 'Nowa kategoria',
    'empty' => 'Nie ma jeszcze kategorii.',
    'empty-help' => 'Kategoria grupuje wpisy. Jeden wpis może być w kilku.',
    'order' => 'Kolejność tutaj to kolejność na stronie',
    'hidden' => 'Ukryta na stronie',
    'no-address' => 'Brak adresu w tym języku',
    'count' => 'Wpisów: :count',
    'show-items' => 'Pokaż jej wpisy',
    'edit' => 'Edytuj',
    'open-on-site' => 'Otwórz na stronie',
    'delete' => 'Usuń',
    'delete-blocked' => 'Dopóki zawiera wpisy, nie można jej usunąć — najpierw je przenieś.',
    'delete-title' => 'Usunąć „:name”?',
    'delete-text' => 'Trafi do kosza i zniknie ze strony, a jej adres się zwolni.',
    'deleted' => 'Kategoria jest w koszu.',
    'cancel' => 'Anuluj',
    'create' => 'Utwórz',
    'save' => 'Zapisz',
    'saved' => 'Zapisano.',
    'save-failed' => 'Nie zapisano — spójrz na zaznaczone pola.',
    'reorder-failed' => 'Nowa kolejność nie została zapisana.',
    'field-title' => 'Nazwa',
    'field-slug' => 'Adres',
    'address-moving' => 'Adres się zmienia. Stary nadal działa i prowadzi do nowego.',
    'untitled' => 'Bez tytułu',
    'trail' => 'Gdzie jesteś',
    'leave-title' => 'Wyjść bez zapisywania?',
    'leave-text' => 'To, co zmieniono tu od ostatniego zapisu, przepadnie.',
    'leave' => 'Wyjdź',
    'field-main' => 'Główna',
    'field-add' => 'Dodaj kategorię',
    'field-remove' => 'Wyjmij z tej kategorii',
    'field-empty' => 'Jeszcze w żadnej kategorii.',
    'field-none-left' => 'Wszystkie kategorie są już wybrane.',
    'order-all' => 'Przeciągnij, aby zmienić kolejność na stronie.',
    'order-category' => 'Przeciągnij, aby zmienić kolejność w tej kategorii. Reszta listy zachowuje swoją.',
    'order-locked' => 'Wyczyść wyszukiwanie i filtry, aby zmienić kolejność — przeciągać można całą listę albo jedną kategorię.',
    'unknown' => 'Jednej z wybranych kategorii już nie ma.',
];
