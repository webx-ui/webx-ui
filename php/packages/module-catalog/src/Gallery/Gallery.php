<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Gallery;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;

/**
 * Puts pictures into a product's gallery (§10.4).
 *
 * Straight onto the catalogue's disk under `catalog/{id div 1000}/{id}/{hash}.{ext}`: a thousand
 * products to a folder, so no directory grows to hundreds of thousands of entries, and the hash
 * as the name, so a picture is never overwritten under a cached address. Uploaded by the editor,
 * or fetched from an address — which is how an import brings them, from its queue.
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

    public function upload(Product $product, UploadedFile $file): ProductImage
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension() ?: 'jpg');

        return $this->store($product, $contents, $extension === 'jpeg' ? 'jpg' : $extension);
    }

    /**
     * @throws ValidationException when the address answers with anything but a picture
     */
    public function fetch(Product $product, string $url): ProductImage
    {
        $limit = (int) config('webx-catalog.images.max_size_kb', 10240) * 1024;

        try {
            $response = Http::timeout(20)->withOptions(['stream' => false])->get($url);
        } catch (Throwable) {
            throw self::refused('image-unreachable');
        }

        $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $contents = $response->body();

        if (! $response->successful()) {
            throw self::refused('image-unreachable');
        }

        if (! isset(self::TYPES[$type]) || @getimagesizefromstring($contents) === false) {
            throw self::refused('image-not-a-picture');
        }

        if (strlen($contents) > $limit) {
            throw self::refused('image-too-large');
        }

        return $this->store($product, $contents, self::TYPES[$type]);
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

    private static function refused(string $key): ValidationException
    {
        return ValidationException::withMessages(['url' => [(string) __('webx-catalog::errors.'.$key)]]);
    }
}
