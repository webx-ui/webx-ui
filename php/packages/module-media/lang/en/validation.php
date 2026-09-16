<?php

declare(strict_types=1);

return [
    'title' => 'name',
    'parent_id' => 'parent folder',
    'directory_id' => 'folder',
    'files' => 'files',
    // A whole gallery arrives under one field name, so what is wrong with the seventh
    // picture has to say "the seventh": nobody can find it otherwise.
    'item' => 'File :number: :message',
    'shape' => 'This is not a file from the library.',
    'accept' => 'This field takes :kind only.',
    'localized' => 'A field of files cannot be translated — the captions inside it are.',
    'kind' => [
        'image' => 'images',
        'video' => 'video',
        'audio' => 'audio',
        'document' => 'documents',
        'other' => 'other files',
    ],
];
