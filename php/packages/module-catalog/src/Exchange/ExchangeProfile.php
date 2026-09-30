<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A saved way to exchange (§6 of the exchange spec): an import's mapping of the file's headers to
 * columns and its settings, or an export's list of columns — so that the supplier's price list of
 * every Monday is one click, not a mapping done again.
 *
 * @property int $id
 * @property string $name
 * @property string $direction
 * @property string $format
 * @property array<string, mixed>|null $options
 * @property array<array-key, mixed>|null $mapping
 * @property int|null $last_run_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExchangeProfile extends Model
{
    public const IMPORT = 'import';

    public const EXPORT = 'export';

    protected $table = 'catalog_exchange_profiles';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'mapping' => 'array',
            'last_run_id' => 'integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toResponse(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'direction' => $this->direction,
            'format' => $this->format,
            'options' => $this->options ?? [],
            'mapping' => $this->mapping ?? [],
            'last_run_id' => $this->last_run_id,
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
