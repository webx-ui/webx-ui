<?php

declare(strict_types=1);

namespace WebxUi\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A method of the site (spec §5): a type from the registry with the site's names, rules and
 * settings. The gateway's keys are encrypted with the site's APP_KEY and never leave the server
 * (§15) — hidden from every array and JSON form of the model.
 *
 * @property int $id
 * @property string $key
 * @property string $type
 * @property array<string, string> $title
 * @property array<string, string>|null $description
 * @property string|null $icon
 * @property bool $enabled
 * @property int $position
 * @property string|null $group_key
 * @property array<string, mixed> $delivery_rule
 * @property int|null $min_amount
 * @property int|null $max_amount
 * @property array<string, mixed> $settings
 * @property array<string, string>|null $credentials
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class PaymentMethod extends Model
{
    protected $table = 'payment_methods';

    protected $guarded = ['id'];

    protected $hidden = ['credentials'];

    protected $attributes = [
        'enabled' => false,
        'position' => 0,
        'delivery_rule' => '{"mode":"any"}',
        'settings' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'enabled' => 'boolean',
            'position' => 'integer',
            'delivery_rule' => 'array',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'settings' => 'array',
            'credentials' => 'encrypted:array',
        ];
    }
}
