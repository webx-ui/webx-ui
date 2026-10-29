<?php

declare(strict_types=1);

namespace WebxUi\Orders\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Localization\HasTranslations;

/**
 * What the buyer sees (spec §3, §5.2): a group of internal statuses with a name in the languages
 * of the site — in the account, in letters, on the order's page. `code` is the stable key for
 * templates and agents; renaming the status leaves it as it was.
 *
 * @property int $id
 * @property string $code
 * @property mixed $name
 * @property mixed $description
 * @property int $position
 * @property array{notify_channels?: list<int>}|null $options
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, OrderStatus> $statuses
 */
class PublicStatus extends Model
{
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'order_public_statuses';

    protected $guarded = [];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['name', 'description'];
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'options' => 'array',
        ];
    }

    /**
     * The internal statuses of this group, in their order.
     *
     * @return HasMany<OrderStatus, $this>
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(OrderStatus::class, 'public_status_id')->orderBy('position');
    }
}
