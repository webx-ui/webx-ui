<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Social networks
    |---------------------------------------------------------------------------
    |
    | What a person's social links may point at: the key is stored with the
    | link, the name is what the panel's select and the block print. Names are
    | brands and are not translated; a site that wants "Personal page" adds a
    | network of its own under its own name.
    |
    | A network taken out of this list drops out of every card on the site but
    | stays in the database: put it back, and the links come back with it. The
    | offered block draws an icon for the seven below; a network the site adds
    | is printed as its name until the site's copy of the block gets an icon.
    |
    */

    'networks' => [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'x' => 'X',
        'telegram' => 'Telegram',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
    ],

];
