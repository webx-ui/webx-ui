<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use WebxUi\Configurator\Catalog\ConfiguratorPart;

/**
 * A product of the catalogue that opens a configurator (§22.2): a row of `configurator_product`,
 * by the product's id — the configurator by its key, and where it starts.
 *
 * The form of the product writes the table through its part ({@see ConfiguratorPart});
 * the model is for what wants a record to point at — the demo's journal, a project's own code.
 *
 * @property int $product_id
 * @property string $configurator
 * @property array<string, string|int|float>|null $preset
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProductLink extends Model
{
    protected $table = Configurator::LINKS;

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = ['product_id', 'configurator', 'preset'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['product_id' => 'integer', 'preset' => 'array'];
    }
}
