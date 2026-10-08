<?php

declare(strict_types=1);
use WebxUi\Media\Images\Optimizing\ScaleDown;

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
    | The panel sends every upload a piece at a time (module-admin's chunked
    | protocol, purpose `media.library`), so this — not PHP's
    | `upload_max_filesize` — is the limit of a file: PHP's only has to fit
    | one piece. Both this and the extensions are checked when an upload
    | starts, before a byte is sent, and the content again once it is whole.
    | `max_files` bounds one multipart request to `POST files`.
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
    | Optimizing
    |---------------------------------------------------------------------------
    |
    | What a JPEG, PNG or still WebP goes through on its way into the library:
    | turned the right way up and stripped of its metadata, then `steps` in
    | order, then encoded at `quality`. An upload becomes `format` (null keeps
    | its own); a picture already in the library keeps its format and its key
    | when «Optimize» runs it through again. A result that is not smaller is
    | thrown away. A step is any class implementing
    | WebxUi\Media\Images\Optimizing\OptimizeStep.
    |
    | «Convert to WebP» (the option in the «Optimize» dialog, `convert: true`
    | of media_optimize_images, `php artisan webx:media:webp`) is off unless
    | asked for: a JPEG or PNG — and a HEIC where Imagick reads it — becomes
    | `format` under a new key (same uuid, new extension), every reference to
    | the old key is rewritten (see `usage.rewrite_ignore`), the old key is
    | kept as an alias so `/storage/media/…/<uuid>.jpg` answers with a 301, and
    | the old bytes go. A picture is left alone when the result is not smaller.
    |
    */

    'optimize' => [
        'enabled' => env('WEBX_MEDIA_OPTIMIZE', true),
        'max_side' => 2560,
        'quality' => 82,
        'format' => 'webp',
        'steps' => [
            ScaleDown::class,
        ],
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

    /*
    |---------------------------------------------------------------------------
    | Fetching from an address
    |---------------------------------------------------------------------------
    |
    | `media_upload_from_url` fetches only from the public internet: an address
    | that is or resolves to loopback, a private network, link-local (cloud
    | metadata) or another reserved range is refused. `allow_hosts` names
    | hosts on the site's own network that may be fetched all the same —
    | exact host names, no wildcards.
    |
    */

    'remote' => [
        'allow_hosts' => [],
    ],

    /*
    |---------------------------------------------------------------------------
    | Where a file is in use
    |---------------------------------------------------------------------------
    |
    | Before an agent deletes a file, every table is searched for it: foreign
    | keys into `media_files`, and the file's key inside text and JSON columns.
    | These tables are skipped — history, logs, queues and the library itself,
    | where a mention is not a use. Patterns as Str::is reads them.
    |
    */

    'usage' => [
        'ignore' => [
            'media_*',
            'migrations',
            'cache', 'cache_locks', 'sessions',
            'jobs', 'job_batches', 'failed_jobs',
            'password_reset_tokens', 'personal_access_tokens', 'oauth_*',
            'mcp_*', 'audit_*', 'admin_history', 'admin_uploads',
            'entity_versions', 'block_versions', 'routes_trashed',
            'catalog_exchange_*', 'catalog_bulk_*', 'catalog_index_queue',
            'telescope_*', 'pulse_*',
        ],

        // What a rewrite of keys passes over («Convert to WebP»). Shorter than
        // `ignore` on purpose: history and versions are rewritten too, so a
        // version restored later does not bring back a key whose bytes are gone.
        'rewrite_ignore' => [
            'media_*',
            'migrations',
            'cache', 'cache_locks', 'sessions',
            'jobs', 'job_batches', 'failed_jobs',
            'password_reset_tokens', 'personal_access_tokens', 'oauth_*',
            'admin_uploads', 'cms_uploads',
            'telescope_*', 'pulse_*',
        ],
    ],

];
