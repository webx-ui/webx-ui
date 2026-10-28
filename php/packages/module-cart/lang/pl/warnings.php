<?php

declare(strict_types=1);

// One phrase a code of Totals\Warning: `line.gone` is ['line']['gone'].
return [
    'line' => [
        'gone' => ':name nie jest już dostępny. Możesz go usunąć.',
        'unavailable' => ':name nie można teraz kupić.',
        'currency' => 'Cena :name jest w innej walucie, tutaj nie można go kupić.',
        'qty_limited' => 'Można kupić tylko :max szt. :name.',
    ],
    'price' => [
        'down' => ':name jest teraz tańszy.',
        'up' => ':name jest teraz droższy.',
    ],
    'tenders' => [
        'capped' => 'Użyto tylko części salda: jest większe niż suma.',
    ],
    'totals' => [
        'source_failed' => 'Części sumy nie udało się policzyć. Spróbuj później.',
    ],
];
