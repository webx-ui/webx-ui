<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Where the files live
    |---------------------------------------------------------------------------
    |
    | The module's own disk rather than the application's default: a site whose
    | own storage is `local` may still want its library on S3, and a client
    | installation usually has nothing but `public`. Anything Laravel's
    | filesystem can address will do — what the module never does is touch a
    | path itself, so a remote disk behaves like a local one.
    |
    | `prefix` is the directory every key starts with, so the library can share
    | a bucket with something else.
    |
    */

    'disk' => env('WEBX_MEDIA_DISK', 'public'),

    'prefix' => env('WEBX_MEDIA_PREFIX', 'media'),

    /*
    |---------------------------------------------------------------------------
    | Addresses for private disks
    |---------------------------------------------------------------------------
    |
    | A disk with a configured `url` answers with it. One without — a private
    | bucket — gets a temporary signed address instead, and this is how long it
    | stays valid.
    |
    */

    'temporary_url_ttl' => 3600,

    /*
    |---------------------------------------------------------------------------
    | Uploads
    |---------------------------------------------------------------------------
    |
    | `max_size` is in kilobytes, the unit Laravel's own validation speaks.
    |
    | The types are a white list of extensions on purpose. A list of what must not be
    | uploaded is always missing one, and the one it misses is usually executable; and a
    | list of extensions is what the person who has to edit it reads, where a list of
    | forty mime types is not. Laravel checks the file's real type against the extension,
    | so a .jpg full of PHP is refused all the same.
    |
    */

    'upload' => [
        'max_size' => (int) env('WEBX_MEDIA_MAX_SIZE', 51200),
        'max_files' => 20,
        'extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg',
            'pdf', 'txt', 'csv', 'rtf',
            'doc', 'docx', 'odt',
            'xls', 'xlsx', 'ods',
            'ppt', 'pptx',
            'zip',
            'mp3', 'ogg', 'wav',
            'mp4', 'webm',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Images
    |---------------------------------------------------------------------------
    |
    | `max_pixels` refuses an image whose width times height is larger than
    | this before it is decoded: a small file can still be a very large
    | picture, and decoding it is how a server runs out of memory.
    |
    */

    'image' => [
        'driver' => env('WEBX_MEDIA_IMAGE_DRIVER', 'gd'),
        'max_pixels' => 50_000_000,
        'quality' => 85,
    ],

    /*
    |---------------------------------------------------------------------------
    | Thumbnails
    |---------------------------------------------------------------------------
    |
    | Variants are cut on demand and cached on the same disk. Only these sizes
    | are allowed — an open parameter would let one request ask the server to
    | resize anything to anything.
    |
    */

    'thumbs' => [
        'widths' => [160, 320, 640, 1280],
        'fits' => ['cover', 'contain'],
    ],

];
