<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Media\Images\Optimizing\LibraryOptimizing;
use WebxUi\Media\Models\MediaFile;

/**
 * «Optimize» in the library: which pictures are waiting, then a few of them per request.
 *
 * The panel walks the list itself rather than handing it to a queue. A batch is a few seconds,
 * so the person pressing the button sees the count go up and can stop it, and nothing depends on
 * a worker being up — or on one that has been up since before the settings changed.
 */
final class OptimizeController
{
    /** Per request: a dozen large photographs decoded one after another is the most one should hold. */
    public const BATCH = 10;

    public function __construct(private readonly LibraryOptimizing $optimizing) {}

    /** The pictures of a folder — or of a selection — that the current settings have not been through. */
    public function pending(Request $request): JsonResponse
    {
        $request->validate([
            'directory_id' => ['nullable', 'integer', 'exists:media_directories,id'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'convert' => ['sometimes', 'boolean'],
        ]);

        // Off unless asked for: converting rewrites every place the site names a picture.
        $query = ($request->boolean('convert') ? $this->optimizing->convertible() : $this->optimizing->pending())->orderBy('id');

        if ($request->filled('ids')) {
            $query->whereIn('id', array_map('intval', (array) $request->input('ids')));
        } elseif ($request->filled('directory_id')) {
            $query->where('directory_id', $request->integer('directory_id'));
        }

        $files = $query->get(['id', 'size']);

        return ApiResponse::data([
            'ids' => $files->pluck('id')->all(),
            'size' => (int) $files->sum('size'),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::BATCH],
            'ids.*' => ['integer'],
            'convert' => ['sometimes', 'boolean'],
        ]);

        $convert = $request->boolean('convert');
        $files = MediaFile::query()->whereIn('id', array_map('intval', (array) $request->input('ids')))->get();

        return ApiResponse::data($files->map(fn (MediaFile $file): array => $convert
            ? $this->optimizing->convert($file)
            : $this->optimizing->run($file)
        )->values()->all());
    }
}
