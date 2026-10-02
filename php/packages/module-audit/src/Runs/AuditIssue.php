<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use WebxUi\Audit\Checks\Finding;

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
 * @property string $key
 * @property int|null $ignored_by
 * @property string|null $fixed_with
 * @property Carbon|null $fixed_at
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
            'fixed_at' => 'datetime',
        ];
    }

    /** The finding as its check yielded it, with its run — what a fix is given. */
    public function finding(): Finding
    {
        return new Finding(
            $this->check,
            $this->severity,
            $this->url,
            $this->details ?? [],
            (string) ($this->key ?? ''),
            $this->page_id,
            $this->run_id,
        );
    }

    /** @return BelongsTo<AuditRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AuditRun::class, 'run_id');
    }

    /**
     * The rule that hides it, if one does.
     *
     * @return BelongsTo<AuditIgnore, $this>
     */
    public function ignore(): BelongsTo
    {
        return $this->belongsTo(AuditIgnore::class, 'ignored_by');
    }
}
