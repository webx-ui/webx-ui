<?php

declare(strict_types=1);

namespace WebxUi\Media;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Contracts\AssetUrls;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Media\Screens\FileFieldType;
use WebxUi\Media\Screens\FilesFieldType;
use WebxUi\Media\Screens\GalleryFieldType;
use WebxUi\Media\Screens\MediaFieldType;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Storage\LibraryUrls;

class MediaServiceProvider extends ServiceProvider
{
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
        $this->mergeConfigFrom(__DIR__.'/../config/webx-media.php', 'webx-media');

        // One lookup behind all four field types: a page of blocks asks about the same library
        // once, and what it asked is thrown away at the end of the response.
        $this->app->singleton(MediaFiles::class);

        // Where a library key lives, for whoever holds one inside a value of their own — the
        // pictures in a `wx-rich-text` document above all. Bound here rather than asked for by
        // name, because the panel may not have a file manager at all.
        $this->app->bind(AssetUrls::class, LibraryUrls::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-media');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->app->make(ModuleRegistry::class)->register(new MediaModule);

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

        $this->app->make(Dispatcher::class)->listen(RequestHandled::class, static function () use ($files): void {
            $files->flush();
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-media.php' => config_path('webx-media.php'),
        ], 'webx-media-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-media'),
        ], 'webx-media-lang');
    }
}
