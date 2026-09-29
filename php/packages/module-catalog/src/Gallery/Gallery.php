<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Gallery;

use finfo;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Uploads\ClaimedUpload;
use WebxUi\Admin\Uploads\Uploads;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Gallery\Video\VideoProvider;
use WebxUi\Catalog\Gallery\Video\VideoProviders;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;

/**
 * Puts pictures and videos into a product's gallery (§10.4 and the video spec).
 *
 * Straight onto the catalogue's disk under `catalog/{id div 1000}/{id}/{hash}.{ext}`: a thousand
 * products to a folder, so no directory grows to hundreds of thousands of entries, and the hash
 * as the name, so a file is never overwritten under a cached address. Uploaded by the editor,
 * or fetched from an address — which is how an import brings them, from its queue.
 *
 * A video is never a row of its own: it is attached to a picture, which becomes its poster
 * (decision 1). A provider's link brings its cover as that picture; a file comes either through
 * the panel's chunked upload or from a direct link, downloaded on the queue.
 *
 * `upload()` and `fetch()` only put things in; `add()`, `remove()`, `attachVideo()` and
 * `detachVideo()` are what the panel and the agent call, and they tell the journal.
 */
final class Gallery
{
    /** What is accepted from an address: the formats a browser shows and the encoder writes. */
    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /** The containers a browser plays in a `<video>`, and the extension each is stored under. */
    private const VIDEO_EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];

    /** What an upload of this purpose is registered under in `module-admin`. */
    public const UPLOAD_PURPOSE = 'catalog.video';

    public function __construct(
        private readonly VideoProviders $providers,
        private readonly Catalog $catalog,
        private readonly Config $config,
    ) {}

    public function videoEnabled(): bool
    {
        return (bool) $this->config->get('webx-catalog.fields.video', true);
    }

    /** @return list<string> */
    public function videoTypes(): array
    {
        $types = array_values(array_map('strval', (array) $this->config->get('webx-catalog.videos.types', array_keys(self::VIDEO_EXTENSIONS))));

        // Only the containers we know how to name: a type added by config without an extension
        // would be stored as a file nothing plays.
        return array_values(array_intersect($types, array_keys(self::VIDEO_EXTENSIONS)));
    }

    public function videoMaxBytes(): int
    {
        return max(1, (int) $this->config->get('webx-catalog.videos.max_size_mb', 2048)) * 1048576;
    }

    /**
     * What an address would become, told without fetching it — for an agent's `dry_run`: a
     * provider's video, a video file for the queue, or, as far as can be told, a picture.
     *
     * @return array{kind: 'video'|'video-file'|'picture', provider?: string, id?: string}
     */
    public function classify(string $url): array
    {
        $video = $this->providers->match($url);

        if ($video !== null) {
            return ['kind' => 'video', 'provider' => $video[0]->key(), 'id' => $video[1]];
        }

        return self::looksLikeVideoFile($url) ? ['kind' => 'video-file'] : ['kind' => 'picture'];
    }

    public function upload(Product $product, UploadedFile $file): ProductImage
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension() ?: 'jpg');

        return $this->store($product, $contents, $extension === 'jpeg' ? 'jpg' : $extension);
    }

    /**
     * A picture, a provider's video with its cover, or a direct link to a video put on the queue.
     *
     * @throws ValidationException when the address answers with anything else, or names a video
     *                             on a site that switched them off
     */
    public function fetch(Product $product, string $url): ProductImage|QueuedVideo
    {
        $video = $this->providers->match($url);

        if ($video !== null) {
            $this->assertVideoEnabled('url');

            return $this->fetchProviderVideo($product, ...$video);
        }

        if (self::looksLikeVideoFile($url)) {
            $this->assertVideoEnabled('url');

            return $this->queue($product, $url);
        }

        $limit = (int) $this->config->get('webx-catalog.images.max_size_kb', 10240) * 1024;

        try {
            $response = Http::timeout(20)->withOptions(['stream' => true])->get($url);
        } catch (Throwable) {
            throw self::refused('url', 'image-unreachable');
        }

        $type = self::mime((string) $response->header('Content-Type'));

        if (! $response->successful()) {
            throw self::refused('url', 'image-unreachable');
        }

        // A link that says nothing about itself and turns out to be a video: the download is the
        // queue's, not this request's.
        if (isset(self::VIDEO_EXTENSIONS[$type])) {
            $this->assertVideoEnabled('url');

            return $this->queue($product, $url);
        }

        $contents = $response->body();

        if (! isset(self::TYPES[$type]) || @getimagesizefromstring($contents) === false) {
            throw self::refused('url', 'image-not-a-picture');
        }

        if (strlen($contents) > $limit) {
            throw self::refused('url', 'image-too-large');
        }

        return $this->store($product, $contents, self::TYPES[$type]);
    }

    /**
     * A file or an address as a new row of the gallery, in the journal as a change of `images`.
     */
    public function add(Product $product, UploadedFile|string $source): ProductImage|QueuedVideo
    {
        return DB::transaction(function () use ($product, $source): ProductImage|QueuedVideo {
            $image = $source instanceof UploadedFile ? $this->upload($product, $source) : $this->fetch($product, $source);

            if ($image instanceof ProductImage) {
                $product->recordHistory(HistoryEntry::UPDATED, [
                    ['field' => 'images', 'from' => null, 'to' => $this->label($image)],
                ]);
                $this->catalog->touch([$product->id]);
            }

            return $image;
        });
    }

    /** The row and its files at once (§10.4): a picture taken off a product is not kept anywhere. */
    public function remove(Product $product, ProductImage $image): void
    {
        DB::transaction(function () use ($product, $image): void {
            $image->delete();

            $product->recordHistory(HistoryEntry::UPDATED, [
                ['field' => 'images', 'from' => $this->label($image), 'to' => null],
            ]);
            $this->catalog->touch([$product->id]);
        });

        // After the commit: files are not transactional, and a rolled-back delete must not have
        // taken them.
        $image->eraseFiles();
    }

    /**
     * A video onto a picture: a finished chunked upload by its id, or an address — a provider's
     * link at once, a direct link to a file on the queue. The video it had before goes, its file
     * with it.
     *
     * @param  mixed  $admin  whoever sends the upload's id; it has to be whoever uploaded it
     *
     * @throws ValidationException when videos are off, or the source is not a video
     */
    public function attachVideo(ProductImage $image, string $source, ?int $duration = null, mixed $admin = null): ProductImage|QueuedVideo
    {
        $isUrl = preg_match('#^https?://#i', $source) === 1;
        $this->assertVideoEnabled($isUrl ? 'url' : 'upload');

        if (! $isUrl) {
            $claimed = app(Uploads::class)->claim($source, self::UPLOAD_PURPOSE, $admin);

            return $this->putVideoFile($image, $claimed->path, $claimed->name, $duration, static fn (string $path) => $claimed->moveTo(Storage::disk(ProductImage::disk()), $path), $claimed);
        }

        $video = $this->providers->match($source);

        if ($video !== null) {
            [$provider, $id] = $video;

            return $this->setVideo($image, $provider->key(), $id, $duration, $provider->label());
        }

        if (self::looksLikeVideoFile($source) || $this->answersWithVideo($source)) {
            return $this->queue($this->productOf($image), $source, $image);
        }

        throw self::refused('url', 'video-not-a-video');
    }

    /** The video off a picture; a file goes from the disk at once. The picture stays. */
    public function detachVideo(ProductImage $image): ProductImage
    {
        if (! $image->hasVideo()) {
            return $image;
        }

        $before = $this->label($image);
        [$path, $provider] = [$image->video, $image->video_provider];

        DB::transaction(function () use ($image, $before): void {
            $image->forceFill(['video_provider' => null, 'video' => null, 'video_duration' => null])->save();

            $this->productOf($image)->recordHistory(HistoryEntry::UPDATED, [
                ['field' => 'images', 'from' => $before, 'to' => null],
            ]);
            $this->catalog->touch([$image->product_id]);
        });

        $image->eraseVideoFileOf($provider, $path);

        return $image;
    }

    /**
     * The queue's half of a direct link (§5): streamed onto the disk under the size limit, checked
     * by what it contains, then attached to the picture — or, with none named, a row of its own
     * behind a plain poster, since the server takes no frames (decision 6).
     *
     * Nothing happens when the product, or the picture, went while the file waited in the queue,
     * or when videos were switched off meanwhile.
     */
    public function download(int $productId, string $url, ?int $imageId = null): ?ProductImage
    {
        $product = Product::query()->find($productId);
        $image = $imageId === null ? null : ProductImage::query()->where('product_id', $productId)->find($imageId);

        if (! $product instanceof Product || ($imageId !== null && ! $image instanceof ProductImage) || ! $this->videoEnabled()) {
            return null;
        }

        $limit = $this->videoMaxBytes();
        $temporary = tempnam(sys_get_temp_dir(), 'webx-video-');

        if ($temporary === false) {
            throw new RuntimeException('No room for a temporary file to download a video into.');
        }

        try {
            try {
                $response = Http::timeout(3600)->withOptions([
                    'sink' => $temporary,
                    // Refused before a byte of the body when the server says how large it is.
                    'on_headers' => static function (ResponseInterface $headers) use ($limit): void {
                        if ((int) $headers->getHeaderLine('Content-Length') > $limit) {
                            throw new RuntimeException('too-large');
                        }
                    },
                    'progress' => static function (int $total, int $downloaded) use ($limit): void {
                        if ($downloaded > $limit) {
                            throw new RuntimeException('too-large');
                        }
                    },
                ])->get($url);
            } catch (Throwable $failed) {
                throw new RuntimeException(str_contains($failed->getMessage(), 'too-large')
                    ? "The video at [{$url}] is larger than ".($limit / 1048576).' MB.'
                    : "The video at [{$url}] could not be downloaded: {$failed->getMessage()}", 0, $failed);
            }

            if (! $response->successful()) {
                throw new RuntimeException("The video at [{$url}] answered {$response->status()}.");
            }

            clearstatcache(true, $temporary);

            $image ??= $this->placeholder($product);
            $name = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'video';

            try {
                return $this->putVideoFile($image, $temporary, $name, null, static function (string $path) use ($temporary): void {
                    $stream = fopen($temporary, 'rb');

                    try {
                        Storage::disk(ProductImage::disk())->writeStream($path, $stream);
                    } finally {
                        is_resource($stream) && fclose($stream);
                    }
                });
            } catch (ValidationException $refused) {
                if ($imageId === null) {
                    $image->eraseFiles();
                    $image->delete();
                }

                throw new RuntimeException("The video at [{$url}] was refused: ".implode(' ', $refused->validator->errors()->all()), 0, $refused);
            }
        } finally {
            @unlink($temporary);
        }
    }

    /**
     * What the journal calls a row: its picture, and the video on it when there is one —
     * `«photo.jpg» ▶ YouTube`.
     */
    public function label(ProductImage $image, ?string $video = null): string
    {
        $picture = basename($image->path);

        if ($video === null && $image->hasVideo()) {
            $video = $image->video_provider === VideoProviders::FILE
                ? basename((string) $image->video)
                : ($this->providers->find((string) $image->video_provider)?->label() ?? (string) $image->video_provider);
        }

        return $video === null ? $picture : "«{$picture}» ▶ {$video}";
    }

    /**
     * @param  (callable(string): void)  $move  puts the checked file at the path it is given
     */
    private function putVideoFile(ProductImage $image, string $local, string $name, ?int $duration, callable $move, ?ClaimedUpload $claimed = null): ProductImage
    {
        $type = (string) (new finfo(FILEINFO_MIME_TYPE))->file($local);
        $size = (int) filesize($local);

        try {
            if (! in_array($type, $this->videoTypes(), true)) {
                throw self::refused('upload', 'video-not-a-video');
            }

            if ($size > $this->videoMaxBytes()) {
                throw self::refused('upload', 'video-too-large', ['max' => (int) ($this->videoMaxBytes() / 1048576)]);
            }
        } catch (ValidationException $refused) {
            $claimed?->discard();

            throw $refused;
        }

        $id = (int) $image->product_id;
        $hash = (string) hash_file('sha1', $local);
        $path = sprintf('catalog/%d/%d/%s.%s', intdiv($id, 1000), $id, $hash, self::VIDEO_EXTENSIONS[$type]);

        $move($path);

        return $this->setVideo($image, VideoProviders::FILE, $path, $duration, $name);
    }

    private function setVideo(ProductImage $image, string $provider, string $video, ?int $duration, string $label): ProductImage
    {
        [$oldPath, $oldProvider] = [$image->video, $image->video_provider];
        $before = $image->hasVideo() ? $this->label($image) : null;

        DB::transaction(function () use ($image, $provider, $video, $duration, $label, $before): void {
            $image->forceFill([
                'video_provider' => $provider,
                'video' => $video,
                'video_duration' => $duration !== null && $duration > 0 ? $duration : null,
            ])->save();

            $this->productOf($image)->recordHistory(HistoryEntry::UPDATED, [
                ['field' => 'images', 'from' => $before, 'to' => $this->label($image, $label)],
            ]);
            $this->catalog->touch([$image->product_id]);
        });

        if ($oldPath !== $video || $oldProvider !== $provider) {
            $image->eraseVideoFileOf($oldProvider, $oldPath);
        }

        return $image;
    }

    private function fetchProviderVideo(Product $product, VideoProvider $provider, string $id): ProductImage
    {
        $picture = null;

        foreach ($provider->posterUrls($id) as $poster) {
            try {
                $response = Http::timeout(20)->get($poster);
            } catch (Throwable) {
                continue;
            }

            $type = self::mime((string) $response->header('Content-Type'));

            if ($response->successful() && isset(self::TYPES[$type]) && @getimagesizefromstring($response->body()) !== false) {
                $picture = [$response->body(), self::TYPES[$type]];

                break;
            }
        }

        if ($picture === null) {
            throw self::refused('url', 'video-no-poster');
        }

        $image = $this->store($product, ...$picture);
        $image->forceFill(['video_provider' => $provider->key(), 'video' => $id])->save();

        $title = $provider->title($id);

        if ($title !== null && trim((string) $image->getTranslation('alt', app()->getLocale())) === '') {
            $image->setTranslation('alt', app()->getLocale(), mb_substr($title, 0, 500));
            $image->save();
        }

        return $image;
    }

    private function queue(Product $product, string $url, ?ProductImage $image = null): QueuedVideo
    {
        FetchVideo::dispatch((int) $product->getKey(), $url, $image?->getKey() === null ? null : (int) $image->getKey())
            ->afterCommit();

        return new QueuedVideo((int) $product->getKey(), $url, $image?->getKey() === null ? null : (int) $image->getKey());
    }

    /**
     * The poster of a video that came without a picture: a plain dark frame, the same for every
     * such row, since the server takes no frames. The editor replaces the row's picture by adding
     * one and attaching the video to it.
     */
    private function placeholder(Product $product): ProductImage
    {
        return $this->store($product, self::placeholderPicture(), 'png');
    }

    private static function placeholderPicture(): string
    {
        if (function_exists('imagecreatetruecolor')) {
            $canvas = imagecreatetruecolor(1280, 720);

            if ($canvas !== false) {
                imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 32, 32, 36));
                imagefilledpolygon($canvas, [590, 300, 590, 420, 700, 360], (int) imagecolorallocate($canvas, 220, 220, 225));

                ob_start();
                imagepng($canvas);

                return (string) ob_get_clean();
            }
        }

        // One dark pixel, where the image extension is missing.
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNQUFAAAABSACl1eIV3AAAAAElFTkSuQmCC');
    }

    private function answersWithVideo(string $url): bool
    {
        try {
            $response = Http::timeout(10)->head($url);
        } catch (Throwable) {
            return false;
        }

        return $response->successful() && isset(self::VIDEO_EXTENSIONS[self::mime((string) $response->header('Content-Type'))]);
    }

    private static function looksLikeVideoFile(string $url): bool
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, self::VIDEO_EXTENSIONS, true);
    }

    private static function mime(string $header): string
    {
        return strtolower(trim(explode(';', $header)[0]));
    }

    private function productOf(ProductImage $image): Product
    {
        return $image->product ?? throw new RuntimeException("The picture [{$image->getKey()}] belongs to no product.");
    }

    private function assertVideoEnabled(string $field): void
    {
        if (! $this->videoEnabled()) {
            throw self::refused($field, 'video-off');
        }
    }

    private function store(Product $product, string $contents, string $extension): ProductImage
    {
        $id = (int) $product->getKey();
        $path = sprintf('catalog/%d/%d/%s.%s', intdiv($id, 1000), $id, sha1($contents), $extension);
        $size = @getimagesizefromstring($contents);

        Storage::disk(ProductImage::disk())->put($path, $contents);

        $position = (int) $product->images()->max('position');

        return ProductImage::query()->create([
            'product_id' => $id,
            'path' => $path,
            'width' => is_array($size) ? $size[0] : null,
            'height' => is_array($size) ? $size[1] : null,
            'size' => strlen($contents),
            'position' => $product->images()->exists() ? $position + 1 : 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    private static function refused(string $field, string $key, array $replace = []): ValidationException
    {
        return ValidationException::withMessages([$field => [(string) __('webx-catalog::errors.'.$key, $replace)]]);
    }
}
