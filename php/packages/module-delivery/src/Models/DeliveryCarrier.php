<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A carrier on the site (§5): whether it is on, its settings and — encrypted, never shown — its
 * API keys. The shop's own row, `store`, is there from the migration on.
 *
 * @property string $key
 * @property bool $enabled
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $credentials
 * @property array<string, mixed>|null $meta
 */
class DeliveryCarrier extends Model
{
    protected $table = 'delivery_carriers';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'enabled', 'settings', 'credentials', 'meta'];

    protected $attributes = [
        'enabled' => true,
    ];

    /** Never in an array or JSON: the panel says «set / replace», MCP says nothing (§15). */
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings' => 'array',
            'credentials' => 'encrypted:array',
            'meta' => 'array',
        ];
    }
}
