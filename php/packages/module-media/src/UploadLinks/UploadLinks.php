<?php

declare(strict_types=1);

namespace WebxUi\Media\UploadLinks;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Carbon;
use JsonException;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaUploadLink;

/**
 * One-time links an agent uploads a file through, with `curl`, past the model.
 *
 * The link is one opaque token in the path, the way a preview link is (`PreviewToken` of
 * module-blocks): a shell, a chat and a copy-paste keep one path segment whole, where a query of
 * `expires=…&signature=…` loses everything after the `&` the moment somebody forgets the quotes.
 * Inside: the row's id and the moment the link ends, signed with the application key. The
 * signature is checked before the database is asked anything; the row is what makes the link
 * one-time and remembers who asked for it.
 *
 * Bound to nothing about the network on purpose: the agent that asks for a link over MCP and the
 * terminal that runs `curl` are often different machines — a chat in the cloud, a shell at home.
 */
final class UploadLinks
{
    private const ALGORITHM = 'sha256';

    public function __construct(
        private readonly Config $config,
        private readonly UrlGenerator $url,
    ) {}

    /**
     * A new link to put one file into `$directory`, for the administrator who asked.
     *
     * @param  array<string, string>|string|null  $alt
     */
    public function issue(MediaDirectory $directory, ?int $adminId, ?int $grantId, ?string $name, array|string|null $alt): IssuedLink
    {
        $this->prune();

        $expires = Carbon::now()->addMinutes($this->minutes())->startOfSecond();

        $link = MediaUploadLink::query()->create([
            'directory_id' => $directory->id,
            'admin_id' => $adminId,
            'grant_id' => $grantId,
            'name' => $name,
            'alt' => $alt,
            'expires_at' => $expires,
        ]);

        return new IssuedLink($link, $this->url->route('webx.media.upload-link', ['token' => $this->token($link)]));
    }

    /**
     * The row a token names, if the token is ours. Expired and used rows are returned too: the
     * caller says which of the two it was, which is what the agent needs to hear.
     */
    public function find(string $token): ?MediaUploadLink
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if ($payload === '' || $signature === '' || ! hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        try {
            $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($claims) || count($claims) !== 2 || ! is_string($claims[0]) || ! is_int($claims[1])) {
            return null;
        }

        $link = MediaUploadLink::query()->find($claims[0]);

        // The moment in the token is the one the row was made with; a row that says otherwise
        // was not the one signed.
        return $link instanceof MediaUploadLink && $link->expires_at->getTimestamp() === $claims[1] ? $link : null;
    }

    /**
     * Take the link for one upload, or say it is taken. One statement, so of two requests sent
     * at once exactly one gets the row: the database decides, not a read followed by a write.
     */
    public function claim(MediaUploadLink $link): bool
    {
        $now = Carbon::now();

        $taken = MediaUploadLink::query()
            ->whereKey($link->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->update(['used_at' => $now, 'updated_at' => $now]);

        if ($taken === 1) {
            $link->used_at = $now;
        }

        return $taken === 1;
    }

    /**
     * Give a claimed link back after the library refused the file — a wrong type, a picture too
     * large to decode — so the agent can send the right one without asking for a new link.
     */
    public function release(MediaUploadLink $link): void
    {
        MediaUploadLink::query()->whereKey($link->id)->whereNull('file_id')->update(['used_at' => null]);
        $link->used_at = null;
    }

    public function used(MediaUploadLink $link, int $fileId): void
    {
        $link->forceFill(['file_id' => $fileId])->save();
    }

    /** The library's own limit of a file, in bytes. */
    public function libraryBytes(): int
    {
        return max(1, (int) $this->config->get('webx-media.upload.max_size', 51200)) * 1024;
    }

    /**
     * The largest file a link takes as `curl -F` (multipart): PHP drops a form file over its
     * `upload_max_filesize` before the application sees a byte, and that limit is the server's,
     * often 2 MB, whatever the library allows.
     */
    public function multipartBytes(): int
    {
        return min(array_filter([$this->libraryBytes(), self::ini('upload_max_filesize'), self::ini('post_max_size')]));
    }

    /**
     * The largest file a link takes as the raw body (`curl -T`): only `post_max_size` stands
     * between it and the library, because no form is parsed.
     */
    public function rawBytes(): int
    {
        return min(array_filter([$this->libraryBytes(), self::ini('post_max_size')]));
    }

    /** A size from php.ini in bytes; null when unlimited or unset. */
    private static function ini(string $key): ?int
    {
        $value = trim((string) ini_get($key));

        if ($value === '' || $value === '0' || $value === '-1') {
            return null;
        }

        $number = (int) $value;
        $bytes = match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };

        return $bytes > 0 ? $bytes : null;
    }

    public function minutes(): int
    {
        return max(1, (int) ($this->config->get('webx-media.upload_links.ttl') ?: 10));
    }

    /**
     * Rows nobody needs: links that ended unused a day ago, and used ones past the days the
     * record is kept. Lazily, when a new link is made — the table grows only through that door.
     */
    public function prune(): void
    {
        $now = Carbon::now();

        MediaUploadLink::query()->whereNull('used_at')->where('expires_at', '<', $now->copy()->subDay())->delete();

        $days = (int) ($this->config->get('webx-media.upload_links.keep_days') ?? 90);

        if ($days > 0) {
            MediaUploadLink::query()->whereNotNull('used_at')->where('used_at', '<', $now->copy()->subDays($days))->delete();
        }
    }

    private function token(MediaUploadLink $link): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode([$link->id, $link->expires_at->getTimestamp()], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $payload.'.'.$this->sign($payload);
    }

    private function sign(string $payload): string
    {
        return hash_hmac(self::ALGORITHM, 'webx-media-upload-link|'.$payload, (string) $this->config->get('app.key'));
    }
}
