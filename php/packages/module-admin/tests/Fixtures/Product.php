<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Localization\HasTranslations;

/**
 * A record whose saves are journalled: a translated name, a price, a flag, a long text, the
 * bounds of a tree and a secret — one column for each rule of the trait.
 *
 * @property int $id
 * @property array<string, string>|null $name
 * @property string|null $price
 * @property bool $is_visible
 * @property string|null $body
 * @property int $lft
 * @property string|null $token
 */
final class Product extends Model
{
    use HasTranslations;
    use RecordsHistory;
    use SoftDeletes;

    protected $table = 'products';

    protected $guarded = [];

    protected $hidden = ['token'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['name'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_visible' => 'boolean',
        ];
    }
}
