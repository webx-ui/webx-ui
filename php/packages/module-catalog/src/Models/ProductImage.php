<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Exceptions\RuntimeException as ImageException;
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
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProductImage extends Model
{
    use HasTranslations;

    protected $table = 'catalog_product_images';

    /** @var list<string> */
    protected $fillable = ['product_id', 'path', 'alt', 'title', 'width', 'height', 'size', 'position'];

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

    /** The files of the picture and every copy of it: a deleted picture takes them at once (§10.4). */
    public function eraseFiles(): void
    {
        app(Thumbnails::class)->forgetOf(self::disk(), $this->path);
        Storage::disk(self::disk())->delete($this->path);
    }
}
