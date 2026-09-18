<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionFile;
use WebxUi\Inbox\Storage\FileStore;

/**
 * An attachment, served by the panel rather than by the disk (§8).
 *
 * The bytes come back through this site's own domain and this site's own permission. The
 * alternative — a signed address to the bucket — is one somebody forwards in a chat, and it
 * keeps working for its own lifetime rather than for as long as the person has `inbox.view`.
 */
final class SubmissionFileController
{
    public function __construct(private readonly FileStore $files) {}

    public function __invoke(Submission $submission, SubmissionFile $file): StreamedResponse|JsonResponse
    {
        // Both are bound by id, so nothing stops a request naming a file of another
        // submission; the permission is the same either way, but the address should not lie.
        if ($file->submission_id !== $submission->getKey()) {
            return new JsonResponse(['message' => __('webx-inbox::errors.file-missing')], 404);
        }

        $disk = $this->files->disk($file->disk);

        if (! $disk->exists($file->path)) {
            return new JsonResponse(['message' => __('webx-inbox::errors.file-missing')], 404);
        }

        return $disk->download($file->path, $file->name, [
            // A visitor's file is never something to keep in a shared cache, and it is never
            // something to open in the tab either: it arrived from a stranger.
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
