<?php

declare(strict_types=1);

namespace WebxUi\Admin\Uploads;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Why an upload, a piece of one or its hand-over did not happen, already in the shape the panel
 * reads: a 422 names its field the way a failed form does, a 409 carries the offset the server
 * really has, so that the client carries on from there instead of guessing.
 *
 * Rendered by the framework wherever it is thrown, so that a consumer calling `claim()` from its
 * own controller answers the same way this package's endpoint does.
 */
final class UploadRefused extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $field = null,
        public readonly ?int $offset = null,
    ) {
        parent::__construct($message);
    }

    public static function unknownPurpose(): self
    {
        return new self((string) __('webx-admin::uploads.no-purpose'), 422, 'purpose');
    }

    public static function forbidden(): self
    {
        return new self((string) __('webx-admin::uploads.forbidden'), 403);
    }

    public static function wrongType(): self
    {
        return new self((string) __('webx-admin::uploads.type'), 422, 'type');
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self((string) __('webx-admin::uploads.too-large', ['max' => self::megabytes($maxBytes)]), 422, 'size');
    }

    public static function noSpace(int $freeBytes): self
    {
        return new self((string) __('webx-admin::uploads.no-space', ['free' => self::megabytes($freeBytes)]), 422, 'size');
    }

    /** Gone, expired, never there or somebody else's — all the same answer, on purpose. */
    public static function missing(): self
    {
        return new self((string) __('webx-admin::uploads.missing'), 404);
    }

    public static function offset(int $offset): self
    {
        return new self((string) __('webx-admin::uploads.offset'), 409, null, $offset);
    }

    public static function overflow(): self
    {
        return new self((string) __('webx-admin::uploads.overflow'), 422, 'upload');
    }

    public static function unfinished(): self
    {
        return new self((string) __('webx-admin::uploads.unfinished'), 422, 'upload');
    }

    public static function wrongPurpose(): self
    {
        return new self((string) __('webx-admin::uploads.wrong-purpose'), 422, 'upload');
    }

    public function render(): JsonResponse
    {
        $body = ['message' => $this->getMessage()];

        if ($this->field !== null) {
            $body['errors'] = [$this->field => [$this->getMessage()]];
        }

        $response = new JsonResponse($body, $this->status);

        if ($this->offset !== null) {
            $response->headers->set('Upload-Offset', (string) $this->offset);
        }

        return $response;
    }

    private static function megabytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 0, '.', ' ').' MB';
    }
}
