<?php

declare(strict_types=1);

// The regions of the layout (the regions spec): the words a declaration may point at with
// `trans::webx-blocks::regions.header`, and what the server says about a region.
return [
    'header' => 'Nagłówek',
    'footer' => 'Stopka',
    'usage' => 'Strefa „:title”',
    'preview-failed' => 'Blok tej strefy zawodzi. Na stronie zamiast całej strefy pojawi się znacznik z kodu.',
    'not-in-registry' => ':path nie jest stroną z rejestru adresów, więc strefa jest pokazana na pustej stronie układu.',
    'too-many' => 'Strefa mieści najwyżej :max bloków.',
    'not-allowed' => 'Bloku „:type” nie można umieścić w tej strefie.',
    'refused' => 'Strefa nie przyjmuje tych bloków.',
    'conflict' => 'Strefa zmieniła się od czasu otwarcia. Odśwież, aby zobaczyć zmiany.',
    'failed-block' => 'Blok „:type” (:key) zawodzi: :reason',
    'not-published' => 'Nie opublikowano: blok szkicu nie daje się wyrenderować.',
    'nothing-to-publish' => 'Strefy nigdy nie zapisano: nie ma czego publikować.',
    'no-version' => 'Strefa nie ma wersji :number.',
    'no-fallback' => 'Układ nie wskazał jeszcze widoku dla tej strefy albo widok zniknął.',
    'adopt-taken' => 'Typ bloku „:slug” już istnieje.',
    'adopt-failed' => 'Nie udało się przenieść znacznika do typu bloku.',
    'adopt-forbidden' => 'Przeniesienie znacznika do typu bloku wymaga prawa edycji typów bloków.',
];
