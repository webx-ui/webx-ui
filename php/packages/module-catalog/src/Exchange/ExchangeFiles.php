<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use WebxUi\Admin\Remote\FetchRefused;
use WebxUi\Admin\Remote\RemoteFetcher;
use WebxUi\Admin\Uploads\Upload;
use WebxUi\Admin\Uploads\UploadRefused;
use WebxUi\Admin\Uploads\Uploads;

/**
 * Where the files of the exchange come from and where they are kept (§4.1, §5 of the exchange
 * spec): an upload in pieces under the purpose `catalog.exchange`, or an address the queue
 * downloads; kept on `exchange.disk` under `catalog-exchange/` until the prune takes them.
 *
 * The readers want a path on this machine, and a disk may be somewhere else: `local()` hands the
 * file over as one, a temporary copy when it has to be.
 */
final class ExchangeFiles
{
    public const UPLOAD_PURPOSE = 'catalog.exchange';

    public const DIRECTORY = 'catalog-exchange';

    public function __construct(private readonly Uploads $uploads) {}

    public function disk(): Filesystem
    {
        return Storage::disk((string) (config('webx-catalog.exchange.disk') ?: 'local'));
    }

    public function maxBytes(): int
    {
        return max(1, (int) config('webx-catalog.exchange.max_bytes', 200 * 1024 * 1024));
    }

    /**
     * A finished upload of this administrator, read where it lies without claiming it — what
     * `inspect` looks at before the import is started and takes the file.
     *
     * @return array{path: string, name: string}
     *
     * @throws ValidationException
     */
    public function peek(string $uploadId, mixed $admin): array
    {
        try {
            $upload = $this->uploads->find($admin, $uploadId);
        } catch (Throwable) {
            throw self::refused('upload_id', 'upload-missing');
        }

        if ($upload->purpose !== self::UPLOAD_PURPOSE || ! $upload->finished()) {
            throw self::refused('upload_id', 'upload-missing');
        }

        return ['path' => $this->uploads->path($upload->id), 'name' => $upload->name];
    }

    /**
     * Take the upload for a run and keep it under the run's name.
     *
     * @return array{file: string, name: string}
     *
     * @throws ValidationException
     */
    public function claim(string $uploadId, mixed $admin, int $runId, string $extension): array
    {
        try {
            $claimed = $this->uploads->claim($uploadId, self::UPLOAD_PURPOSE, $admin);
        } catch (UploadRefused) {
            throw self::refused('upload_id', 'upload-missing');
        }

        $file = self::DIRECTORY.'/imports/'.$runId.'.'.$extension;
        $claimed->moveTo($this->disk(), $file);

        return ['file' => $file, 'name' => $claimed->name];
    }

    /** A file on this machine — a test's, a console command's — copied in under the run's name. */
    public function adopt(string $path, int $runId, string $extension): string
    {
        $file = self::DIRECTORY.'/imports/'.$runId.'.'.$extension;
        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException("The file [{$path}] cannot be read.");
        }

        try {
            $this->disk()->writeStream($file, $stream);
        } finally {
            is_resource($stream) && fclose($stream);
        }

        return $file;
    }

    /**
     * An address an import may be given: the switch `exchange.url_import` on, http(s), and a host
     * `exchange.url_hosts` lets through when it names any. The network is the download's to
     * check, on every redirect; this is what can be said before the run is queued.
     *
     * @throws ValidationException
     */
    public function assertUrl(string $url): void
    {
        if (! (bool) config('webx-catalog.exchange.url_import', true)) {
            throw self::refused('url', 'url-off');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw self::refused('url', 'not-a-url', ['url' => $url]);
        }

        try {
            $this->fetcher()->check($url);
        } catch (FetchRefused $refused) {
            throw self::refused('url', 'unreachable', ['reason' => $refused->getMessage()]);
        }
    }

    /**
     * Download an address into a temporary file through the site's {@see RemoteFetcher}: public
     * addresses only, checked on every redirect; refused past `exchange.max_bytes` before the body
     * when the server says how large it is, and as soon as it grows past it when not.
     *
     * What came back has to look like a file of the exchange — a spreadsheet or text, never a
     * page — or it is refused without a word of it: `inspect` shows the header and five rows of
     * whatever it is given, and an error page read back that way is an answer the site was never
     * meant to hand out.
     *
     * @throws RuntimeException with words for the run's errors
     */
    public function download(string $url): string
    {
        if (! (bool) config('webx-catalog.exchange.url_import', true)) {
            throw new RuntimeException((string) __('webx-catalog::exchange.errors.url-off'));
        }

        $limit = $this->maxBytes();
        $temporary = tempnam(sys_get_temp_dir(), 'webx-exchange-');

        if ($temporary === false) {
            throw new RuntimeException('No room for a temporary file to download into.');
        }

        try {
            $fetched = $this->fetcher()->download($url, $temporary, $limit, timeout: 600);
        } catch (FetchRefused $refused) {
            throw new RuntimeException($refused->reason === FetchRefused::TOO_LARGE
                ? (string) __('webx-catalog::exchange.errors.too-large', ['max' => intdiv($limit, 1048576)])
                : (string) __('webx-catalog::exchange.errors.unreachable', ['reason' => $refused->getMessage()]), 0, $refused);
        }

        if (! self::looksLikeExchange($temporary, $fetched->contentType)) {
            @unlink($temporary);

            throw new RuntimeException((string) __('webx-catalog::exchange.errors.not-a-table', ['type' => $fetched->contentType ?? '?']));
        }

        return $temporary;
    }

    /**
     * A spreadsheet (a zip) or text that is not a web page: what the formats read. A CSV with a
     * stray byte still reads; a page, an image or an archive of something else does not.
     */
    public static function looksLikeExchange(string $path, ?string $contentType): bool
    {
        if (in_array($contentType, ['text/html', 'application/xhtml+xml'], true)) {
            return false;
        }

        $head = (string) @file_get_contents($path, length: 8192);

        if (str_starts_with($head, "PK\x03\x04")) {
            return true;
        }

        if (str_contains($head, "\0") && ! str_starts_with($head, "\xFF\xFE") && ! str_starts_with($head, "\xFE\xFF")) {
            return false;
        }

        $start = strtolower(ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $head) ?? $head));

        foreach (['<!doctype', '<html', '<head', '<body', '<?xml', '<svg', '<script'] as $page) {
            if (str_starts_with($start, $page)) {
                return false;
            }
        }

        return true;
    }

    private function fetcher(): RemoteFetcher
    {
        /** @var list<string> $hosts */
        $hosts = array_values(array_map('strval', (array) config('webx-catalog.exchange.url_hosts', [])));

        return app(RemoteFetcher::class)->onlyHosts($hosts);
    }

    /**
     * Do `$work` with the file as a path on this machine.
     *
     * @template T
     *
     * @param  Closure(string): T  $work
     * @return T
     */
    public function local(string $file, Closure $work): mixed
    {
        $disk = $this->disk();

        // A local disk hands out its own path; any other gives a key that is not a file here.
        if (is_file($local = $disk->path($file))) {
            return $work($local);
        }

        $temporary = tempnam(sys_get_temp_dir(), 'webx-exchange-');
        $stream = $disk->readStream($file);

        if ($temporary === false || $stream === null) {
            throw new RuntimeException("The file [{$file}] of the exchange is gone.");
        }

        try {
            file_put_contents($temporary, $stream);

            return $work($temporary);
        } finally {
            is_resource($stream) && fclose($stream);
            @unlink($temporary);
        }
    }

    public static function extension(string $name): string
    {
        return strtolower(pathinfo(parse_url($name, PHP_URL_PATH) ?: $name, PATHINFO_EXTENSION));
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function refused(string $field, string $key, array $replace = []): ValidationException
    {
        return ValidationException::withMessages([$field => [(string) __('webx-catalog::exchange.errors.'.$key, $replace)]]);
    }
}
