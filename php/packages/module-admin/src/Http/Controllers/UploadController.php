<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Uploads\UploadRefused;
use WebxUi\Admin\Uploads\Uploads;

/**
 * The four requests of a chunked upload (§4 of the video spec).
 *
 *     POST   uploads        { name, size, type, fingerprint, purpose } → { id, offset, size, chunk_size }
 *     HEAD   uploads/{id}   → Upload-Offset: n
 *     PATCH  uploads/{id}   raw bytes, Upload-Offset: n → 204, Upload-Offset: n + length
 *     DELETE uploads/{id}   → 204
 *
 * Every session is its administrator's alone: another one asking about the id gets a 404,
 * the same as for an id that never was.
 */
final class UploadController
{
    public function __construct(private readonly Uploads $uploads) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:1024'],
            'size' => ['required', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'max:255'],
            'fingerprint' => ['required', 'string', 'max:2048'],
            'purpose' => ['required', 'string', 'max:64'],
        ]);

        [$upload, $created] = $this->uploads->start(
            $request->user(),
            (string) $data['purpose'],
            (string) $data['name'],
            (int) $data['size'],
            (string) ($data['type'] ?? ''),
            (string) $data['fingerprint'],
        );

        return ApiResponse::data($upload->describe($this->uploads->chunkSize()), $created ? 201 : 200)
            ->header('Upload-Offset', (string) $upload->offset);
    }

    /** HEAD and GET both: the header for the client that only wants to know where to resume. */
    public function show(Request $request, string $id): JsonResponse
    {
        $upload = $this->uploads->find($request->user(), $id);

        return ApiResponse::data($upload->describe($this->uploads->chunkSize()))
            ->header('Upload-Offset', (string) $upload->offset)
            ->header('Cache-Control', 'no-store');
    }

    public function append(Request $request, string $id): Response
    {
        $upload = $this->uploads->find($request->user(), $id);
        $offset = $request->header('Upload-Offset');

        if (! is_string($offset) || preg_match('/^\d+$/', $offset) !== 1) {
            // Without the offset the piece could land anywhere; the client is told where to send it.
            throw UploadRefused::offset($upload->offset);
        }

        $length = $request->header('Content-Length');
        $body = $request->getContent(true);

        $upload = $this->uploads->append(
            $upload,
            (int) $offset,
            $body,
            is_string($length) && is_numeric($length) ? (int) $length : null,
        );

        return new Response('', 204, ['Upload-Offset' => (string) $upload->offset]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->uploads->cancel($this->uploads->find($request->user(), $id));

        return ApiResponse::noContent();
    }
}
