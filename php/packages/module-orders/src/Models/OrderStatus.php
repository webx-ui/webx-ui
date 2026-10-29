<?php

declare(strict_types=1);

namespace WebxUi\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Localization\HasTranslations;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\StatusColor;

/**
 * A working stage of the shop (spec §3, §5.2) — «the storekeeper is looking» — which the buyer
 * never sees: they see its public group. Moving an order here may set a milestone (decision 13);
 * leaving does not take it back.
 *
 * @property int $id
 * @property int $public_status_id
 * @property mixed $name
 * @property StatusColor $color
 * @property int $position
 * @property Milestone|null $sets_milestone
 * @property bool $is_active
 * @property Carbon|null $deleted_at
 * @property-read PublicStatus $publicStatus
 */
class OrderStatus extends Model
{
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'order_statuses';

    protected $guarded = [];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['name'];
    }

    protected function casts(): array
    {
        return [
            'public_status_id' => 'integer',
            'color' => StatusColor::class,
            'position' => 'integer',
            'sets_milestone' => Milestone::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * The group the buyer sees, deleted too: orders in this status keep showing its name (§5.6).
     *
     * @return BelongsTo<PublicStatus, $this>
     */
    public function publicStatus(): BelongsTo
    {
        return $this->belongsTo(PublicStatus::class, 'public_status_id')->withTrashed();
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'status_id');
    }
}
