<?php

declare(strict_types=1);

namespace WebxUi\Admin\Uploads;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One file on its way in (§4 of the video spec): what it is, whose it is, and how far it has got.
 *
 * @property string $id
 * @property int $admin_id
 * @property string $purpose
 * @property string $name
 * @property int $size
 * @property string $type
 * @property string $fingerprint
 * @property int $offset
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Upload extends Model
{
    use HasUuids;

    protected $table = 'cms_uploads';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'admin_id' => 'integer',
            'size' => 'integer',
            'offset' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function finished(): bool
    {
        return $this->offset >= $this->size;
    }

    public function expired(?Carbon $now = null): bool
    {
        return $this->expires_at->lessThanOrEqualTo($now ?? Carbon::now());
    }

    /**
     * What the panel is told about a session: enough to carry on from where it stopped.
     *
     * @return array{id: string, offset: int, size: int, chunk_size: int}
     */
    public function describe(int $chunkSize): array
    {
        return [
            'id' => $this->id,
            'offset' => $this->offset,
            'size' => $this->size,
            'chunk_size' => $chunkSize,
        ];
    }
}
