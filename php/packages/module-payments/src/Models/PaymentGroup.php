<?php

declare(strict_types=1);

namespace WebxUi\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A manager's edits of a group (groups spec §2.1), never the group itself: which groups there are
 * is the site's config. A row appears with the first edit, and an empty field means «as in the
 * config». A row whose key left the config stays and is ignored.
 *
 * @property int $id
 * @property string $key
 * @property array<string, string>|null $title
 * @property array<string, string>|null $description
 * @property string|null $pictogram
 * @property int|null $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class PaymentGroup extends Model
{
    protected $table = 'payment_groups';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'position' => 'integer',
        ];
    }
}
