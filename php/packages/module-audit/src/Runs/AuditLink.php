<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One address a page points at (§4, `audit_links`): a link, a picture, a script, a form, a URL
 * in a style or in the structured data — everything that leads somewhere, each with the class of
 * its host (decision 12).
 *
 * @property int $id
 * @property int $run_id
 * @property int $from_page_id
 * @property string $to_url
 * @property int|null $to_page_id
 * @property int|null $resource_id
 * @property string $kind
 * @property string|null $anchor
 * @property string|null $rel
 * @property string|null $target
 * @property string|null $host
 * @property string|null $host_class
 * @property bool $absolute
 * @property int|null $status
 */
class AuditLink extends Model
{
    public const A = 'a';

    public const IMG = 'img';

    public const SRCSET = 'srcset';

    public const SCRIPT = 'script';

    public const LINK = 'link';

    public const IFRAME = 'iframe';

    public const FORM = 'form';

    public const STYLE = 'style';

    public const META = 'meta';

    public const JSON_LD = 'json_ld';

    /** The `rel` of a picture whose <picture> already offers WebP or AVIF. */
    public const MODERN = 'modern';

    /** What a visitor's browser loads with the page — mixed content is about these. */
    public const RESOURCES = [self::IMG, self::SRCSET, self::SCRIPT, self::LINK, self::IFRAME, self::STYLE];

    protected $table = 'audit_links';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'to_page_id' => 'integer',
            'resource_id' => 'integer',
            'absolute' => 'boolean',
            'status' => 'integer',
        ];
    }

    /** @return BelongsTo<AuditPage, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(AuditPage::class, 'from_page_id');
    }

    /** @return BelongsTo<AuditPage, $this> */
    public function to(): BelongsTo
    {
        return $this->belongsTo(AuditPage::class, 'to_page_id');
    }

    /** @return BelongsTo<AuditResource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(AuditResource::class, 'resource_id');
    }

    /** A `<link>` that names another address rather than loading one: canonical, hreflang. */
    public function pointer(): bool
    {
        return $this->kind === self::LINK && in_array($this->rel, ['canonical', 'alternate', 'next', 'prev'], true);
    }
}
