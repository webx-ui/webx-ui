<?php

declare(strict_types=1);

namespace WebxUi\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A line of the order's history (spec §3, §8): a move between statuses, a module's note, a
 * failed payment, a block written, a visitor linked. The public ids are the ones of that moment —
 * the grouping may be changed later — and none of the ids is a key: a status deleted by hand in
 * the database must not take the history with it.
 *
 * @property int $id
 * @property int $order_id
 * @property string $kind
 * @property int|null $from_status_id
 * @property int|null $to_status_id
 * @property int|null $from_public_id
 * @property int|null $to_public_id
 * @property string $source
 * @property int|null $admin_id
 * @property string|null $note
 * @property Carbon|null $created_at
 */
class StatusChange extends Model
{
    /** The kinds of a line, as `kind` holds them. */
    public const string STATUS = 'status';

    public const string NOTE = 'note';

    public const string PAYMENT_FAILED = 'payment_failed';

    public const string PAYMENT_AWAITING_STOPPED = 'payment_awaiting_stopped';

    public const string BLOCK = 'block';

    public const string VISITOR = 'visitor';

    /** Contacts, the recipient, the comment or the manager note changed; `note` lists the fields. */
    public const string DETAILS = 'details';

    /** A refund record reversed (decision 98); `note` is «refund#<id>: <reason>». */
    public const string REFUND_REVERSED = 'refund_reversed';

    /** A milestone taken back — only «refunded», by a reversal; `note` is the milestone. */
    public const string MILESTONE_REMOVED = 'milestone_removed';

    /** A message sent again by hand (decision 99); `note` is «<event>: <recipients>». */
    public const string NOTIFICATION_SENT = 'notification_sent';

    protected $table = 'order_status_changes';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'from_status_id' => 'integer',
            'to_status_id' => 'integer',
            'from_public_id' => 'integer',
            'to_public_id' => 'integer',
            'admin_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
