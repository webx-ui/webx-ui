<?php

declare(strict_types=1);

return [
    'kind' => 'Typ jest albo blokiem, albo komponentem.',
    'kind-in-use' => 'Blok stoi na stronach i nie może stać się komponentem. Stron: :count.',
    'unknown-call' => '„:type” nie jest typem bloku: wywołanie nic nie wypisze na stronie.',
    'dynamic-call' => 'Wywoływany typ nie jest zapisany dosłownie: jego publikacja nie sprawdzi tego szablonu.',
    'delete-used-by' => 'Inne typy wywołują ten, a w ich szablonach zostałaby luka. Najpierw usuń wywołania.',
    'delete-used-by-one' => 'Wywoływany przez „:title” (:slug)',
    'publish-cycle' => 'Typy wywołują się nawzajem w kółko: :path.',
    'publish-breaks-parent' => 'Psuje „:parent” na jego przykładzie: :reason',
    'publish-breaks-parent-on' => 'Psuje „:parent” na „:entity”: :reason',
    'publish-breaks-declared' => 'Psuje miejsce, z którego wywołuje go moduł :module: :reason',
    'customise-exists' => 'Typ o tym identyfikatorze już istnieje.',
    'customised-from' => 'Z :view',
];
