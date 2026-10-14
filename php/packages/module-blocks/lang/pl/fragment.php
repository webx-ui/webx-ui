<?php

declare(strict_types=1);

return [
    'choose' => 'Wybierz wspólny blok',
    'none' => 'Witryna nie ma jeszcze wspólnych bloków. Programista deklaruje go w regions pliku config/webx-blocks.php z fragment ustawionym na true.',
    'missing' => '„:name” nie jest już wspólnym blokiem witryny: nic tu nie jest wyświetlane.',
    'status-live' => 'Na stronie',
    'status-edits' => 'Na stronie, czekają zmiany',
    'status-fallback' => 'Nieopublikowany: witryna pokazuje wariant zapasowy',
    'status-empty' => 'Pusty',
    'explain' => 'Ten blok pokazuje wspólny blok witryny. Jego edycja zmieni go na każdej stronie, na której stoi.',
    'edit' => 'Edytuj dla wszystkich stron',
    'detach' => 'Uczyń go własnym dla tej strony',
    'detach-title' => 'Uczynić „:title” własnym dla tej strony?',
    'detach-text' => 'Jego bloki zostaną skopiowane na tę stronę w jego miejsce — takie, jakie są na stronie. Przestaną podążać za wspólnym blokiem: jego zmiany nie dotrą już do tej strony.',
    'detached' => 'Bloki „:title” są teraz własne dla tej strony.',
    'unpublished' => 'Wspólny blok nie jest jeszcze opublikowany: nie ma czego kopiować.',
    'group-layout' => 'Nagłówek i stopka',
    'group-shared' => 'Wspólne bloki',
    'group-shared-help' => 'Edytowane tu raz i pokazywane wszędzie, gdzie umieści je strona lub szablon witryny.',
    'count' => 'Stron: :count',
    'used-on' => 'Gdzie stoi',
    'used-nowhere' => 'Żadna strona jeszcze go nie umieszcza.',
    'templates' => 'Szablony witryny, które drukują go tagiem, nie są tu widoczne.',
    'draft-only' => 'W wersji roboczej',
    'show-here' => 'Podgląd na tej stronie',
    'publish-text' => 'Każda strona, która go umieszcza, pokaże te bloki zamiast widoku z kodu.',
];
