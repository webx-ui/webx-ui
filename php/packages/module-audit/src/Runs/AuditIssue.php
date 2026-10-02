<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One finding of one run, as stored.
 *
 * @property int $id
 * @property int $run_id
 * @property string $check
 * @property string $severity
 * @property int|null $page_id
 * @property string|null $url
 * @property array<string, mixed>|null $details
 * @property string $fingerprint
 * @property string $state
 * @property int|null $ignored_by
 */
class AuditIssue extends Model
{
    public const NEW = 'new';

    public const PERSISTING = 'persisting';

    protected $table = 'audit_issues';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'page_id' => 'integer',
            'ignored_by' => 'integer',
        ];
    }

    /** @return BelongsTo<AuditRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AuditRun::class, 'run_id');
    }
}
