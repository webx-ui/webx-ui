<?php

declare(strict_types=1);

namespace WebxUi\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Settings\Settings;

/**
 * One row per key. The value is whatever the field type stores; the model neither knows nor
 * cares — the screen does.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    protected $table = 'cms_settings';

    protected $fillable = ['key', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // `json`, not `array`: a setting is as often a string as a record.
        return ['value' => 'json'];
    }

    protected static function booted(): void
    {
        // The table is cached whole, and {@see Settings::save()} is not the only thing that
        // writes it: a seeder, a removal, a console one-liner all go through the model. A row
        // written while the cache stands would be invisible for a day.
        $forget = static function (): void {
            app(Settings::class)->forget();
        };

        static::saved($forget);
        static::deleted($forget);
    }
}
