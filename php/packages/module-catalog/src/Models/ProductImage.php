<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Exceptions\RuntimeException as ImageException;
use WebxUi\Catalog\Gallery\Video\VideoProviders;
use WebxUi\Localization\HasTranslations;
use WebxUi\Media\Images\MissingSource;
use WebxUi\Media\Images\Thumbnails;

/**
 * One picture of a product's gallery (§10.4).
 *
 * A file on the catalogue's own disk rather than a row of the media library: a shop has half a
 * million of these and no editor wants them in a tree. What it keeps of the library is the part
 * an editor does touch — an `alt` and a `title` in every language.
 *
 * @property int $id
 * @property int $product_id
 * @property string $path
 * @property array<string, string>|string|null $alt
 * @property array<string, string>|string|null $title
 * @property int|null $width
 * @property int|null $height
 * @property int|null $size
 * @property string|null $video_provider
 * @property string|null $video
 * @property int|null $video_duration
 * @property int $position
 * @property string|null $source_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProductImage extends Model
{
    use HasTranslations;

    protected $table = 'catalog_product_images';

    /** @var list<string> */
    protected $fillable = ['product_id', 'path', 'alt', 'title', 'width', 'height', 'size', 'position', 'video_provider', 'video', 'video_duration'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
            'video_duration' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['alt', 'title'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    /** The disk every picture of the gallery lives on. */
    public static function disk(): string
    {
        return (string) (config('webx-catalog.images.disk') ?: 'public');
    }

    /** The address of the picture itself. The name is its hash, so it never needs a `?v=`. */
    public function url(): string
    {
        return Storage::disk(self::disk())->url($this->path);
    }

    /**
     * The address of a smaller copy, cut the first time it is asked for. Null when the file is not
     * there to cut from — a picture lost on the disk is an empty square, not a failed page.
     */
    public function thumbUrl(int $width, ?int $height = null, string $fit = 'contain'): ?string
    {
        try {
            $path = app(Thumbnails::class)->variantOf(self::disk(), $this->path, $width, $height, $fit);
        } catch (MissingSource|ImageException) {
            return null;
        }

        return Storage::disk(self::disk())->url($path);
    }

    public function hasVideo(): bool
    {
        return $this->video_provider !== null && $this->video !== null && $this->video !== '';
    }

    public function hasVideoFile(): bool
    {
        return $this->hasVideo() && $this->video_provider === VideoProviders::FILE;
    }

    /**
     * The video as the API, the storefront and the agent read it (§5 of the video spec): `url` is
     * the file or the provider's page of the video, `embed` the iframe's address — null for a
     * file, which plays in a `<video>`. Null without a video, and for a provider nobody registers
     * any more: what cannot be played is not offered.
     *
     * @return array{provider: string, url: string, embed: string|null, duration: int|null}|null
     */
    public function videoData(): ?array
    {
        if (! $this->hasVideo()) {
            return null;
        }

        $id = (string) $this->video;

        if ($this->video_provider === VideoProviders::FILE) {
            return ['provider' => VideoProviders::FILE, 'url' => Storage::disk(self::disk())->url($id), 'embed' => null, 'duration' => $this->video_duration];
        }

        $provider = app(VideoProviders::class)->find((string) $this->video_provider);

        if ($provider === null) {
            return null;
        }

        return ['provider' => $provider->key(), 'url' => $provider->watchUrl($id), 'embed' => $provider->embedUrl($id), 'duration' => $this->video_duration];
    }

    /**
     * The files of the picture, every copy of it and its video: a deleted picture takes them at
     * once (§10.4).
     */
    public function eraseFiles(): void
    {
        app(Thumbnails::class)->forgetOf(self::disk(), $this->path);
        Storage::disk(self::disk())->delete($this->path);
        $this->eraseVideoFile();
    }

    public function eraseVideoFile(): void
    {
        $this->eraseVideoFileOf($this->video_provider, $this->video);
    }

    /**
     * A video file this row held before — replaced or taken off — unless another row holds the
     * same one: a name is the hash of the content, so the same clip uploaded twice is one file.
     */
    public function eraseVideoFileOf(?string $provider, ?string $path): void
    {
        if ($provider !== VideoProviders::FILE || $path === null || $path === '') {
            return;
        }

        $shared = self::query()
            ->where('video_provider', VideoProviders::FILE)
            ->where('video', $path)
            ->whereKeyNot($this->getKey())
            ->exists();

        if (! $shared) {
            Storage::disk(self::disk())->delete($path);
        }
    }
}
