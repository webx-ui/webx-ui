<?php

declare(strict_types=1);

/*
 * A field that points at records of another section (`wx-relations`), and the same choice as a
 * block's filter ("only related to" in `wx-collection`). The keys are the panel's: the English
 * here is what `messages.ts` carries.
 */

return [
    'field-add' => 'Dodaj…',
    'field-searching' => 'Szukam…',
    'field-nothing' => 'Nic nie znaleziono.',
    'field-empty' => 'Jeszcze nic nie wybrano.',
    'field-remove' => 'Usuń',
    'field-drag' => 'Przeciągnij, aby zmienić kolejność',
    'field-hidden' => 'Nie na stronie',
    'field-trashed' => 'W koszu',
    'field-missing' => 'Nie znaleziono',
    'field-full' => 'Można wybrać najwyżej :max.',
    'field-forbidden' => 'Nie widzisz tych wpisów, więc wyboru nie da się tu zmienić.',
    'collection-related' => 'Tylko powiązane z',
    'collection-related-to' => 'Tylko powiązane z „:target”',
    'collection-related-type' => 'Która sekcja',
    'collection-related-any' => 'Bez zawężenia: wszystkie wpisy, powiązane i nie.',
    'collection-related-current' => 'Wpisem strony, na której stoi',
    'collection-related-current-hint' => 'Na stronie wpisu z „:target” blok pokaże to, co jest z nim powiązane; na każdej innej stronie — nic.',
];
