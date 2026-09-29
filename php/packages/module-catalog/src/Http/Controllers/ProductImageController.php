<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Gallery\Gallery;
use WebxUi\Catalog\Gallery\QueuedVideo;
use WebxUi\Catalog\Http\Resources\ImageResource;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;

/**
 * The gallery of a product (§10.4, §11.2): add a picture from a file or from an address, put
 * them in order and caption them, attach a video to one or take it off, take one away.
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

    /**
     * `multipart: file` or `{ url }`. An address may be a video (§5 of the video spec): a
     * provider's link answers with the new row, its cover as the picture; a direct link to a
     * file answers 202 — the download is the queue's, and the row appears once it is done.
     */
    public function store(Request $request, int $product): JsonResponse
    {
        $found = $this->product($product);
        $maxKb = (int) config('webx-catalog.images.max_size_kb', 10240);

        $request->validate([
            'file' => ['required_without:url', 'file', 'image', 'max:'.$maxKb],
            'url' => ['required_without:file', 'nullable', 'url:http,https', 'max:2000'],
        ]);

        $file = $request->file('file');
        $added = $this->gallery->add($found, $file instanceof UploadedFile ? $file : (string) $request->input('url'));

        return $added instanceof QueuedVideo
            ? ApiResponse::data($added->toArray(), 202)
            : ApiResponse::data(new ImageResource($added), 201);
    }

    /**
     * `{ upload, duration? }` — a finished chunked upload of the purpose `catalog.video` — or
     * `{ url }`: a video onto a picture, in place of the one it had. A direct link to a file is
     * queued and answers 202, like adding one.
     */
    public function attachVideo(Request $request, int $product, int $image): JsonResponse
    {
        $picture = $this->image($product, $image);

        $validated = $request->validate([
            'upload' => ['required_without:url', 'nullable', 'string', 'max:64'],
            'url' => ['required_without:upload', 'nullable', 'url:http,https', 'max:2000'],
            'duration' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ]);

        $source = is_string($validated['upload'] ?? null) && $validated['upload'] !== '' ? $validated['upload'] : (string) ($validated['url'] ?? '');
        $duration = isset($validated['duration']) ? (int) $validated['duration'] : null;
        $attached = $this->gallery->attachVideo($picture, $source, $duration, $request->user());

        return $attached instanceof QueuedVideo
            ? ApiResponse::data($attached->toArray(), 202)
            : ApiResponse::data(new ImageResource($attached));
    }

    /** The video off a picture; a file goes from the disk at once, the picture stays. */
    public function detachVideo(int $product, int $image): JsonResponse
    {
        return ApiResponse::data(new ImageResource($this->gallery->detachVideo($this->image($product, $image))));
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
        $this->gallery->remove($this->product($product), $this->image($product, $image));

        return ApiResponse::noContent();
    }

    private function image(int $product, int $image): ProductImage
    {
        return $this->product($product)->images()->whereKey($image)->first() ?? throw new NotFoundHttpException;
    }

    private function product(int $id): Product
    {
        return Product::query()->find($id) ?? throw new NotFoundHttpException;
    }
}
