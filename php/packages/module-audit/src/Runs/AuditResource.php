<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One address the pages load or lead to elsewhere, asked once per run (§3, stage 5): a picture,
 * a stylesheet, a script, an icon, an Open Graph picture, an external page. The links that point
 * at it carry its id.
 *
 * @property int $id
 * @property int $run_id
 * @property string $url
 * @property string $url_hash
 * @property string $kind
 * @property string|null $host_class
 * @property Carbon|null $checked_at
 * @property string|null $method
 * @property int|null $status
 * @property string|null $error
 * @property string|null $location
 * @property string|null $content_type
 * @property int|null $bytes
 * @property string|null $cache_control
 * @property string|null $compression
 * @property int|null $width
 * @property int|null $height
 * @property int|null $total_ms
 */
class AuditResource extends Model
{
    public const IMAGE = 'image';

    public const OG = 'og';

    public const ICON = 'icon';

    public const CSS = 'css';

    public const JS = 'js';

    public const OTHER = 'other';

    public const PAGE = 'page';

    /** Pictures of every sort — the images tab and the picture checks read these. */
    public const PICTURES = [self::IMAGE, self::OG, self::ICON];

    protected $table = 'audit_resources';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'status' => 'integer',
            'bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'total_ms' => 'integer',
        ];
    }

    /** Asked, and nothing answered — no DNS, refused, timed out. */
    public function unreachable(): bool
    {
        return $this->checked_at !== null && $this->status === null;
    }

    /** Asked, and it does not open: 4xx, 5xx or nothing at all. */
    public function broken(): bool
    {
        return $this->unreachable() || ($this->status !== null && $this->status >= 400);
    }
}
