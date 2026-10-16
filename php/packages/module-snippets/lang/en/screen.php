<?php

declare(strict_types=1);

return [
    'name' => 'Name',
    'name-help' => 'For administrators only; never shown on the site.',
    'place' => 'Place',
    'place-help' => 'Where on the page the code is printed. Places of your own are declared in config/webx-snippets.php.',
    'code' => 'Code',
    'code-help' => 'Paste exactly what the service gave you, without <html>, <head> or <body>. document.write does not work in a code that waits for consent.',
    'settings' => 'Settings',
    'enabled' => 'Enabled',
    'enabled-help' => 'An enabled code is on the site as soon as it is saved.',
    'consent' => 'Cookie consent',
    'consent-help' => 'Anything but "Necessary" is printed only after the visitor agrees to that category.',
    'on-stage' => 'On dev and stage too',
    'on-stage-help' => 'Off: the code is printed only in production, so a copy of the site never counts visits.',
    'coverage' => 'Where it is printed',
    'only-paths' => 'Only on',
    'only-paths-help' => 'Paths without the language, * for anything: /, /blog/*. Empty means the whole site.',
    'except-paths' => 'Except',
    'except-paths-help' => 'Paths where the code is never printed, like /blog/drafts/*.',
    'locales' => 'Languages',
    'locales-help' => 'None ticked means every language.',
    'history' => 'History',
    'position' => 'Order',
];
