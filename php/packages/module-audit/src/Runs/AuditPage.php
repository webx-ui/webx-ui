<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The snapshot of one address of one run (§4, `audit_pages`): what it answered, what its
 * `<head>` says, how much text it has and how it is linked. The page checks read these rows, the
 * pages screen lists them, and a row with no `fetched_at` is still waiting for the crawler.
 *
 * @property int $id
 * @property int $run_id
 * @property string $url
 * @property string $url_hash
 * @property string $source
 * @property int|null $depth
 * @property Carbon|null $fetched_at
 * @property int|null $final_status
 * @property string|null $redirect_to
 * @property int|null $status
 * @property string|null $error
 * @property string|null $content_type
 * @property int|null $bytes
 * @property int|null $ttfb_ms
 * @property int|null $total_ms
 * @property string|null $compression
 * @property array<string, string>|null $headers
 * @property bool $blocked_by_robots
 * @property bool $indexable
 * @property string|null $title
 * @property string|null $description
 * @property list<string>|null $h1
 * @property array<string, int>|null $headings
 * @property string|null $canonical
 * @property string|null $robots_meta
 * @property string|null $x_robots_tag
 * @property string|null $lang
 * @property list<array{lang: string, url: string}>|null $hreflang
 * @property array<string, string>|null $og
 * @property array<string, string>|null $twitter
 * @property list<array{types: list<string>, error: string|null, items?: list<array{type: string, missing: list<string>, recommended: list<string>}>, source?: string}>|null $json_ld
 * @property int|null $word_count
 * @property string|null $text_hash
 * @property int $links_in
 * @property int $links_out_internal
 * @property int $links_out_external
 * @property int $images
 * @property int $images_without_alt
 * @property bool $in_sitemap
 * @property bool $in_registry
 * @property array<string, mixed>|null $facts
 */
class AuditPage extends Model
{
    public const HOME = 'home';

    public const SITEMAP = 'sitemap';

    public const REGISTRY = 'registry';

    public const LINK = 'link';

    protected $table = 'audit_pages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'fetched_at' => 'datetime',
            'final_status' => 'integer',
            'status' => 'integer',
            'bytes' => 'integer',
            'ttfb_ms' => 'integer',
            'total_ms' => 'integer',
            'headers' => 'array',
            'blocked_by_robots' => 'boolean',
            'indexable' => 'boolean',
            'h1' => 'array',
            'headings' => 'array',
            'hreflang' => 'array',
            'og' => 'array',
            'twitter' => 'array',
            'json_ld' => 'array',
            'word_count' => 'integer',
            'links_in' => 'integer',
            'links_out_internal' => 'integer',
            'links_out_external' => 'integer',
            'images' => 'integer',
            'images_without_alt' => 'integer',
            'in_sitemap' => 'boolean',
            'in_registry' => 'boolean',
            'facts' => 'array',
        ];
    }

    public static function hash(string $url): string
    {
        return sha1($url);
    }

    /** An HTML page that answered 200 — what the checks of the markup look at. */
    public function html(): bool
    {
        return $this->status === 200 && self::isHtml($this->content_type);
    }

    public static function isHtml(?string $contentType): bool
    {
        return $contentType !== null && (str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml'));
    }

    /** `noindex` in the meta tag or in the header. */
    public function noindex(): bool
    {
        return str_contains(strtolower(($this->robots_meta ?? '').','.($this->x_robots_tag ?? '')), 'noindex');
    }

    /**
     * HTML pages that answered 200.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeHtml(Builder $query): Builder
    {
        return $query->where('status', 200)->where(
            static fn (Builder $type) => $type->where('content_type', 'like', '%text/html%')->orWhere('content_type', 'like', '%xhtml%'),
        );
    }

    public function fact(string $key, mixed $default = null): mixed
    {
        return ($this->facts ?? [])[$key] ?? $default;
    }

    /** @return HasMany<AuditIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(AuditIssue::class, 'page_id');
    }

    /** @return HasMany<AuditLink, $this> */
    public function outgoing(): HasMany
    {
        return $this->hasMany(AuditLink::class, 'from_page_id');
    }

    /** @return HasMany<AuditLink, $this> */
    public function incoming(): HasMany
    {
        return $this->hasMany(AuditLink::class, 'to_page_id');
    }
}
