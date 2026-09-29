<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Gallery\Gallery;
use WebxUi\Catalog\Http\Resources\ImageResource;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;

/**
 * The gallery of a product (§10.4, §11.2): add a picture from a file or from an address, put
 * them in order and caption them, take one away.
 *
 * Each change is its own request rather than part of the form's save, because a file is not a
 * value: it lands on the disk the moment it is uploaded, and a form that holds it until "Save"
 * holds megabytes in the browser. The journal still hears about it — a picture added or taken
 * away is a change to the product like any other.
 */
final class ProductImageController
{
    public function __construct(
        private readonly Gallery $gallery,
        private readonly Catalog $catalog,
    ) {}

    /** `multipart: file` or `{ url }`. */
    public function store(Request $request, int $product): JsonResponse
    {
        $found = $this->product($product);
        $maxKb = (int) config('webx-catalog.images.max_size_kb', 10240);

        $request->validate([
            'file' => ['required_without:url', 'file', 'image', 'max:'.$maxKb],
            'url' => ['required_without:file', 'nullable', 'url:http,https', 'max:2000'],
        ]);

        $file = $request->file('file');

        $image = DB::transaction(function () use ($found, $file, $request): ProductImage {
            $image = $file instanceof UploadedFile
                ? $this->gallery->upload($found, $file)
                : $this->gallery->fetch($found, (string) $request->input('url'));

            $found->recordHistory(HistoryEntry::UPDATED, [
                ['field' => 'images', 'from' => null, 'to' => basename($image->path)],
            ]);
            $this->catalog->touch([$found->id]);

            return $image;
        });

        return ApiResponse::data(new ImageResource($image), 201);
    }

    /**
     * The whole gallery at once: its order is the order of the list, and each item may carry
     * `alt` and `title` as maps of languages. Every picture of the product has to be in the list —
     * a reorder that leaves one out is a reorder of a gallery the editor is not looking at.
     */
    public function update(Request $request, int $product): JsonResponse
    {
        $found = $this->product($product);

        $validated = $request->validate([
            'images' => ['present', 'array'],
            'images.*.id' => ['required', 'integer'],
            'images.*.alt' => ['nullable', 'array'],
            'images.*.alt.*' => ['nullable', 'string', 'max:500'],
            'images.*.title' => ['nullable', 'array'],
            'images.*.title.*' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var list<array{id: int, alt?: array<string, string|null>|null, title?: array<string, string|null>|null}> $items */
        $items = $validated['images'];
        $images = $found->images()->get()->keyBy('id');
        $sent = array_map(static fn (array $item): int => (int) $item['id'], $items);

        sort($sent);
        $known = $images->keys()->map(static fn (mixed $id): int => (int) $id)->sort()->values()->all();

        if ($sent !== $known) {
            throw ValidationException::withMessages(['images' => [(string) __('webx-catalog::errors.images-mismatch')]]);
        }

        DB::transaction(function () use ($items, $images, $found): void {
            foreach ($items as $position => $item) {
                /** @var ProductImage $image */
                $image = $images[(int) $item['id']];
                $image->position = $position;

                foreach (['alt', 'title'] as $field) {
                    if (array_key_exists($field, $item) && is_array($item[$field])) {
                        $image->setTranslations($field, [...$image->getTranslations($field), ...$item[$field]]);
                    }
                }

                $image->save();
            }

            $this->catalog->touch([$found->id]);
        });

        return ApiResponse::data(ImageResource::collection($found->images()->get()));
    }

    /** The row and the files at once (§10.4): a picture taken off a product is not kept anywhere. */
    public function destroy(int $product, int $image): JsonResponse
    {
        $found = $this->product($product);
        $picture = $found->images()->whereKey($image)->first() ?? throw new NotFoundHttpException;

        DB::transaction(function () use ($found, $picture): void {
            $picture->delete();

            $found->recordHistory(HistoryEntry::UPDATED, [
                ['field' => 'images', 'from' => basename($picture->path), 'to' => null],
            ]);
            $this->catalog->touch([$found->id]);
        });

        // After the commit: files are not transactional, and a rolled-back delete must not have
        // taken them.
        $picture->eraseFiles();

        return ApiResponse::noContent();
    }

    private function product(int $id): Product
    {
        return Product::query()->find($id) ?? throw new NotFoundHttpException;
    }
}
