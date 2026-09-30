<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;
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
     * Download an address into a temporary file, refusing past `exchange.max_bytes` before the
     * body when the server says how large it is, and as soon as it grows past it when not.
     *
     * @throws RuntimeException with words for the run's errors
     */
    public function download(string $url): string
    {
        $limit = $this->maxBytes();
        $temporary = tempnam(sys_get_temp_dir(), 'webx-exchange-');

        if ($temporary === false) {
            throw new RuntimeException('No room for a temporary file to download into.');
        }

        try {
            $response = Http::timeout(600)->withOptions([
                'sink' => $temporary,
                'on_headers' => static function (ResponseInterface $headers) use ($limit): void {
                    if ((int) $headers->getHeaderLine('Content-Length') > $limit) {
                        throw new RuntimeException('too-large');
                    }
                },
                'progress' => static function (int $total, int $downloaded) use ($limit): void {
                    if ($downloaded > $limit) {
                        throw new RuntimeException('too-large');
                    }
                },
            ])->get($url);
        } catch (Throwable $failed) {
            @unlink($temporary);

            throw new RuntimeException(str_contains($failed->getMessage(), 'too-large')
                ? (string) __('webx-catalog::exchange.errors.too-large', ['max' => intdiv($limit, 1048576)])
                : (string) __('webx-catalog::exchange.errors.unreachable', ['reason' => $failed->getMessage()]), 0, $failed);
        }

        if (! $response->successful()) {
            @unlink($temporary);

            throw new RuntimeException((string) __('webx-catalog::exchange.errors.unreachable', ['reason' => 'HTTP '.$response->status()]));
        }

        return $temporary;
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
