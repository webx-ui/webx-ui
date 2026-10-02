<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A finding hidden on purpose (decision 9): `noindex` on the search page, a long title on a
 * landing. The check, an address or a mask, the reason and who decided — every later run hides
 * what it matches the same way.
 *
 * @property int $id
 * @property string $check
 * @property string $pattern
 * @property string $reason
 * @property string|null $created_by
 * @property Carbon|null $created_at
 */
class AuditIgnore extends Model
{
    protected $table = 'audit_ignores';

    protected $guarded = ['id'];

    public function hides(string $check, ?string $url): bool
    {
        return $check === $this->check && Mask::matches($this->pattern, $url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPanel(): array
    {
        return [
            'id' => $this->id,
            'check' => $this->check,
            'pattern' => $this->pattern,
            'reason' => $this->reason,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
