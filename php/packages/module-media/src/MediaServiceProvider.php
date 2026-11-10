<?php

declare(strict_types=1);

namespace WebxUi\Media;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Contracts\AssetDownloads;
use WebxUi\Admin\Contracts\AssetUrls;
use WebxUi\Admin\Events\StoredContentRewritten;
use WebxUi\Admin\Help\HelpRegistry;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Admin\Uploads\UploadPurposes;
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Fixes\AuditFixes;
use WebxUi\Media\Audit\HeavyImages;
use WebxUi\Media\Audit\MissingFiles;
use WebxUi\Media\Audit\OrphanThumbnailsCheck;
use WebxUi\Media\Audit\PruneThumbnailsFix;
use WebxUi\Media\Console\ConvertToWebpCommand;
use WebxUi\Media\Console\PruneThumbnailsCommand;
use WebxUi\Media\Console\SanitizeSvgCommand;
use WebxUi\Media\Http\Controllers\OldAddressController;
use WebxUi\Media\Mcp\UploadNotAccepted;
use WebxUi\Media\Screens\FileFieldType;
use WebxUi\Media\Screens\FilesFieldType;
use WebxUi\Media\Screens\GalleryFieldType;
use WebxUi\Media\Screens\MediaFieldType;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Media\Storage\LibraryUrls;
use WebxUi\Media\UploadLinks\UploadLinks;
use WebxUi\Media\Usage\DatabaseUsage;
use WebxUi\Media\Usage\MediaUsage;

class MediaServiceProvider extends ServiceProvider
{
    /** The rate limiter of the upload links' endpoint. */
    public const UPLOAD_LINK_LIMITER = 'webx-media-upload-link';

    /**
     * What a screen — or a block — can say about a file, and the class that means it.
     *
     * @var array<string, class-string<FieldType>>
     */
    private const FIELD_TYPES = [
        'wx-media' => MediaFieldType::class,
        'wx-gallery' => GalleryFieldType::class,
        'wx-file' => FileFieldType::class,
        'wx-files' => FilesFieldType::class,
    ];

    public function register(): void
    {
        // What moves between stands with webx:snapshot, and what stays where it is.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('media_files', 'media_directories', 'media_aliases');
            // Links made on this stand, for this stand's agents: nothing another stand can use.
            $tables->stand('media_upload_links');
        });

        $this->mergeConfigFrom(__DIR__.'/../config/webx-media.php', 'webx-media');

        // One lookup behind all four field types: a page of blocks asks about the same library
        // once, and what it asked is thrown away at the end of the response.
        $this->app->singleton(MediaFiles::class);

        // Where a library key lives, for whoever holds one inside a value of their own — the
        // pictures in a `wx-rich-text` document above all. Bound here rather than asked for by
        // name, because the panel may not have a file manager at all.
        $this->app->bind(AssetUrls::class, LibraryUrls::class);
        // The same answer with the name a file was uploaded under, for a link that downloads it.
        $this->app->bind(AssetDownloads::class, LibraryUrls::class);

        // Where a file is in use, asked before it is deleted. A module that keeps files where the
        // schema cannot show them tags a source of its own with the same name.
        $this->app->tag([DatabaseUsage::class], MediaUsage::TAG);
    }

    public function boot(): void
    {
        $this->app->make(HelpRegistry::class)->directory(__DIR__.'/../resources/help');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-media');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->oldAddresses();
        $this->uploadLinks();

        $this->app->make(ModuleRegistry::class)->register($this->app->make(MediaModule::class));

        // Uploads arrive a piece at a time through the panel's own protocol, so a dropped
        // connection costs a piece rather than the file, and PHP's request limits stop mattering.
        // The extensions and the size are the multipart endpoint's, read when a session starts.
        $this->app->make(UploadPurposes::class)->register(
            FileStore::UPLOAD_PURPOSE,
            permission: ['media.upload', 'media.manage'],
            maxBytes: static fn (): int => max(1, (int) config('webx-media.upload.max_size', 51200)) * 1024,
            extensions: static fn (): array => array_values(array_map('strval', (array) config('webx-media.upload.extensions', []))),
        );

        // The library's own checks of the site audit, when the audit is installed (§7 of its
        // spec): files the disk lost, images too heavy for a page, and previews of files long gone.
        if (class_exists(AuditChecks::class)) {
            $checks = $this->app->make(AuditChecks::class);
            $checks->register($this->app->make(MissingFiles::class));
            $checks->register($this->app->make(HeavyImages::class));
            $checks->register($this->app->make(OrphanThumbnailsCheck::class));
            $this->app->make(AuditFixes::class)->register($this->app->make(PruneThumbnailsFix::class));
        }

        // What a screen means by these names, on the server: the keys the fields store and the
        // addresses the site reads. The front end registers the same names for the components.
        $types = $this->app->make(FieldTypes::class);

        foreach (self::FIELD_TYPES as $name => $class) {
            /** @var FieldType $type */
            $type = $this->app->make($class);

            $types->register($name, $type);
        }

        // The lookup remembers the rows one response asked about; in a process that serves many
        // responses that memory would outlive the library it describes.
        $files = $this->app->make(MediaFiles::class);

        $events = $this->app->make(Dispatcher::class);

        $events->listen(RequestHandled::class, static function () use ($files): void {
            $files->flush();
        });

        // Keys rewritten in the database directly: the lookup of this response forgets them too.
        $events->listen(StoredContentRewritten::class, static function () use ($files): void {
            $files->flush();
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([ConvertToWebpCommand::class, PruneThumbnailsCommand::class, SanitizeSvgCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-media.php' => config_path('webx-media.php'),
        ], 'webx-media-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-media'),
        ], 'webx-media-lang');
    }

    /**
     * The upload links' endpoint: its rate limit, and its one refusal that happens before any
     * route runs — a body over `post_max_size`, which PHP drops and Laravel's global middleware
     * turns into an HTML error page. curl on a terminal gets JSON it can read instead.
     */
    private function uploadLinks(): void
    {
        RateLimiter::for(self::UPLOAD_LINK_LIMITER, static function (Request $request): Limit {
            [$requests, $minutes] = array_map('intval', array_pad(explode(',', (string) (config('webx-media.upload_links.throttle') ?: '30,1')), 2, 1));

            return Limit::perMinutes(max(1, $minutes), max(1, $requests))->by(self::UPLOAD_LINK_LIMITER.'|'.$request->ip());
        });

        $this->callAfterResolving(ExceptionHandler::class, static function (ExceptionHandler $handler): void {
            if (! $handler instanceof Handler) {
                return;
            }

            $handler->renderable(static function (PostTooLargeException $tooLarge, Request $request): ?JsonResponse {
                if ($request->route()?->getName() !== 'webx.media.upload-link'
                    && ! $request->is(trim((string) config('webx-admin.api_path'), '/').'/media/upload-links/*')) {
                    return null;
                }

                return new JsonResponse([
                    'ok' => false,
                    'error' => UploadNotAccepted::SIZE,
                    'message' => 'The request is larger than this server takes ('.app(UploadLinks::class)->rawBytes().' bytes).',
                ], 413);
            });
        });
    }

    /**
     * Where the public addresses of the library's disk start on this site — `/storage/media` for
     * the `public` disk — so that the address of a key a file no longer has redirects to the one
     * it has now ({@see OldAddressController}). Nothing for a disk this application does not
     * serve: an S3 bucket answers for itself.
     */
    private function oldAddresses(): void
    {
        $disk = (string) config('webx-media.disk', 'public');
        $settings = (array) config("filesystems.disks.{$disk}", []);

        if (($settings['driver'] ?? null) !== 'local' || empty($settings['url'])) {
            return;
        }

        $base = trim((string) parse_url((string) $settings['url'], PHP_URL_PATH), '/');
        $prefix = trim((string) config('webx-media.prefix', 'media'), '/');
        $path = ltrim($base.'/'.$prefix, '/');

        Route::get($path.'/{key}', OldAddressController::class)
            ->where('key', '[A-Za-z0-9/_.-]+\.[A-Za-z0-9]+')
            ->name('webx.media.old-address');
    }
}
