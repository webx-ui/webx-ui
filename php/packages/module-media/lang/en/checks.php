<?php

declare(strict_types=1);

return [
    'media' => [
        'missing_file' => [
            'title' => 'Library files missing on the disk',
            'found' => 'The media library lists a file the disk does not have.',
            'why' => 'Every page and field that uses it shows a broken image or a dead download link.',
            'fix' => 'Upload the file again in the media library, or copy the storage folder from where the site came from.',
        ],
        'heavy' => [
            'title' => 'Images too heavy for a page',
            'found' => 'Images in the media library weigh more than the limit.',
            'why' => 'A page that shows one loads slowly on a phone, and search engines rank slow pages lower.',
            'fix' => 'Replace them with smaller versions: a photo for a page rarely needs to be wider than 2000 pixels or heavier than a few hundred kilobytes.',
        ],
    ],
];
