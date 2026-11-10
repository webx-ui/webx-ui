<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use WebxUi\Admin\History\HistoryContext;
use WebxUi\Mcp\Grants\Grant;
use WebxUi\Media\Mcp\MediaTools;
use WebxUi\Media\Mcp\UploadNotAccepted;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaUploadLink;
use WebxUi\Media\UploadLinks\UploadLinks;

/**
 * Where `curl` sends a file through a link `media_upload_link` made.
 *
 * Not a form of the panel: no session, no CSRF, no `cms.auth` — the signed one-time link is the
 * whole of the credential, and it was issued to an administrator who held `media.upload` at the
 * moment. The file goes into the library by the road every other upload takes
 * ({@see MediaTools::receive()}), in the journal's context of that administrator and that
 * connection, so the upload is theirs wherever it is recorded.
 *
 * Two bodies are taken: a form with a `file` field (`curl -F file=@photo.jpg`), and the file as
 * the raw body (`curl -T photo.jpg`) — PHP refuses a form file over its `upload_max_filesize`,
 * often 2 MB, before the application sees it, and a raw body is held to `post_max_size` only.
 *
 * Every answer is JSON, refusals included, with a code a program can branch on and a sentence
 * the agent can repeat to the person: whoever reads it is `curl` on a terminal, not a browser.
 */
final class UploadLinkController
{
    public function __construct(
        private readonly UploadLinks $links,
        private readonly HistoryContext $context,
    ) {}

    public function __invoke(Request $request, string $token): JsonResponse
    {
        $link = $this->links->find($token);

        if (! $link instanceof MediaUploadLink) {
            return self::refuse(403, 'link_invalid', 'This upload link is not valid. Ask media_upload_link for a new one.');
        }

        if ($link->used_at !== null) {
            return self::refuse(410, 'link_used', 'This upload link has already been used: a link takes one file. Ask media_upload_link for a new one.');
        }

        if ($link->expires_at->isPast()) {
            return self::refuse(410, 'link_expired', 'This upload link has expired. Ask media_upload_link for a new one.');
        }

        [$allowed, $admin] = $this->admin($link);

        if (! $allowed) {
            return self::refuse(403, 'link_invalid', 'The administrator or the connection this link was made for is no longer active.');
        }

        $directory = $link->directory;

        if (! $directory instanceof MediaDirectory) {
            return self::refuse(410, 'link_invalid', 'The folder this link uploads into is gone. Ask media_upload_link for a new one.');
        }

        [$path, $name, $temporary, $refusal] = $this->incoming($request);

        if ($refusal !== null) {
            return $refusal;
        }

        try {
            // Taken only now that there is a file to put in: a request that brought nothing
            // costs the agent nothing.
            if (! $this->links->claim($link)) {
                return self::refuse(410, 'link_used', 'This upload link has already been used: a link takes one file. Ask media_upload_link for a new one.');
            }

            $arguments = $link->alt !== null ? ['alt' => $link->alt] : [];

            try {
                $answer = $this->context->during(
                    HistoryContext::MCP,
                    $admin,
                    $link->grant_id,
                    static fn (): array => MediaTools::receive((string) $path, $link->name ?? $name, $directory, $arguments),
                );
            } catch (UploadNotAccepted $refused) {
                $this->links->release($link);

                return self::refuse($refused->reason === UploadNotAccepted::SIZE ? 413 : 422, $refused->reason, $refused->getMessage());
            }

            $this->links->used($link, (int) $answer['id']);

            return new JsonResponse($answer, $answer['duplicate'] ? 200 : 201);
        } finally {
            if ($temporary) {
                @unlink((string) $path);
            }
        }
    }

    /**
     * The file in the request, as a path on disk and the name it came under.
     *
     * @return array{0: string|null, 1: string, 2: bool, 3: JsonResponse|null} path, name, whether the path is ours to delete, a refusal
     */
    private function incoming(Request $request): array
    {
        $file = $request->file('file');

        if ($file instanceof UploadedFile) {
            if (! $file->isValid()) {
                return in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                    ? [null, '', false, self::refuse(413, UploadNotAccepted::SIZE, 'The file is larger than this server takes as a form field ('
                        .$this->links->multipartBytes().' bytes). Send it as the raw body instead: curl -T <path> "<the same link>" (up to '.$this->links->rawBytes().' bytes).')]
                    : [null, '', false, self::refuse(422, 'no_file', 'The file did not arrive whole. Send it again.')];
            }

            return [(string) $file->getRealPath(), $file->getClientOriginalName(), false, null];
        }

        if (str_starts_with(strtolower((string) $request->header('Content-Type')), 'multipart/form-data')) {
            return [null, '', false, self::refuse(422, 'no_file', 'The form has no `file` field: curl -F file=@<path> "<link>".')];
        }

        return $this->raw($request);
    }

    /**
     * The raw body into a temporary file, counted as it is copied: a body over the library's
     * limit is stopped at the limit rather than written out whole first.
     *
     * @return array{0: string|null, 1: string, 2: bool, 3: JsonResponse|null}
     */
    private function raw(Request $request): array
    {
        $body = $request->getContent(true);
        $temporary = tempnam(sys_get_temp_dir(), 'webx-media-link');

        if (! is_resource($body) || $temporary === false) {
            return [null, '', false, self::refuse(500, 'not_accepted', 'There is no temporary file to receive the upload into.')];
        }

        $out = fopen($temporary, 'wb');
        $limit = $this->links->libraryBytes();
        $written = 0;

        while ($out !== false && ! feof($body)) {
            $chunk = fread($body, 1 << 16);

            if ($chunk === false) {
                break;
            }

            $written += strlen($chunk);

            if ($written > $limit) {
                fclose($out);
                @unlink($temporary);

                return [null, '', false, self::refuse(413, UploadNotAccepted::SIZE, "The file is larger than the library takes ({$limit} bytes).")];
            }

            fwrite($out, $chunk);
        }

        if ($out !== false) {
            fclose($out);
        }

        if ($written === 0) {
            @unlink($temporary);

            return [null, '', false, self::refuse(422, 'no_file', 'Nothing arrived. Send the file: curl -F file=@<path> "<link>", or curl -T <path> "<link>".')];
        }

        $name = trim((string) $request->query('name', ''));

        return [$temporary, $name !== '' ? basename($name) : 'file', true, null];
    }

    /**
     * Whoever asked for the link, if they still may act: an account switched off, or a
     * connection the person ended since, is the end of its links too. A link made with nobody
     * behind it — the local stdio server, where there is no request — acts as nobody.
     *
     * @return array{0: bool, 1: Authenticatable|null}
     */
    private function admin(MediaUploadLink $link): array
    {
        if ($link->admin_id === null) {
            return [true, null];
        }

        /** @var class-string<Model> $model */
        $model = (string) config('webx-auth.model');
        $admin = class_exists($model) ? $model::query()->find($link->admin_id) : null;

        if (! $admin instanceof Authenticatable || ($admin instanceof Model && $admin->getAttribute('is_active') === false)) {
            return [false, null];
        }

        if ($link->grant_id !== null && Grant::query()->find($link->grant_id)?->isRevoked() !== false) {
            return [false, null];
        }

        return [true, $admin];
    }

    private static function refuse(int $status, string $error, string $message): JsonResponse
    {
        return new JsonResponse(['ok' => false, 'error' => $error, 'message' => $message], $status);
    }
}
