<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Catalog;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartField;
use WebxUi\Catalog\Parts\PartSchema;
use WebxUi\Catalog\Parts\ProductPart;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorsModule;
use WebxUi\Configurator\Schema\Validator;

/**
 * The configurator a product opens, in the product's form (§22.2): `configurator.key` — the key,
 * as a block keeps it — and `configurator.preset`, where it starts, `{ step: value, field: number }`.
 * Kept in `configurator_product`, a row per product; no key is no row.
 *
 * The catalogue knows nothing of this: the part is registered into its registry from the
 * configurator's side, and with it `catalog_products_update` takes the two fields as any
 * satellite's (`catalog://product-parts`).
 */
final class ConfiguratorPart implements ProductPart
{
    public const string KEY = 'configurator';

    public function key(): string
    {
        return self::KEY;
    }

    public function describe(): PartSchema
    {
        return new PartSchema('webx-configurator::product.card', ConfiguratorsModule::ID, [
            new PartField('key', 'string', 'webx-configurator::product.configurator', ['nullable', 'string', 'max:64'], 'configurators_list'),
            new PartField('preset', 'object', 'webx-configurator::product.preset', ['nullable', 'array'], 'configurators_get — { step key: option value, size field key: number }; what the schema does not have is dropped on the site'),
        ]);
    }

    public function rules(): array
    {
        return [
            'key' => ['nullable', 'string', 'max:64', static function (string $attribute, mixed $value, Closure $fail): void {
                // The bin included: a configurator binned for a day keeps the products that open it.
                if (is_string($value) && trim($value) !== '' && ! Configurator::withTrashed()->where('key', trim($value))->exists()) {
                    $fail((string) __('webx-configurator::product.unknown', ['key' => trim($value)]));
                }
            }],
            'preset' => ['nullable', 'array'],
        ];
    }

    public function read(Collection $products): array
    {
        $values = [];

        foreach ($products as $product) {
            $values[(int) $product->id] = ['key' => null, 'preset' => null];
        }

        $rows = DB::table(Configurator::LINKS)->whereIn('product_id', $products->modelKeys())->get();

        foreach ($rows as $row) {
            $values[(int) $row->product_id] = [
                'key' => (string) $row->configurator,
                'preset' => self::decode($row->preset),
            ];
        }

        return $values;
    }

    public function write(Product $product, array $input): array
    {
        if (! array_key_exists('key', $input) && ! array_key_exists('preset', $input)) {
            return [];
        }

        $row = DB::table(Configurator::LINKS)->where('product_id', $product->id)->first();
        $before = $row === null ? null : (string) $row->configurator;
        $beforePreset = $row === null ? null : self::decode($row->preset);

        $key = array_key_exists('key', $input) ? (is_string($input['key']) && trim($input['key']) !== '' ? trim($input['key']) : null) : $before;
        $preset = array_key_exists('preset', $input) ? self::preset($input['preset']) : $beforePreset;

        if ($key === null) {
            $preset = null;
        }

        if ($key === $before && $preset == $beforePreset) {
            return [];
        }

        if ($key === null) {
            DB::table(Configurator::LINKS)->where('product_id', $product->id)->delete();
        } else {
            $now = Carbon::now();

            DB::table(Configurator::LINKS)->updateOrInsert(['product_id' => $product->id], [
                'configurator' => $key,
                'preset' => $preset === null ? null : json_encode($preset, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $now,
            ]);
        }

        $changes = [];

        if ($key !== $before) {
            $changes[] = ['field' => self::KEY.'.key', 'from' => $before, 'to' => $key, 'label' => (string) __('webx-configurator::product.configurator')];
        }

        if ($preset != $beforePreset) {
            $changes[] = ['field' => self::KEY.'.preset', 'from' => $beforePreset, 'to' => $preset, 'label' => (string) __('webx-configurator::product.preset')];
        }

        return $changes;
    }

    /**
     * What a preset keeps: a value for a step, a number for a size, under a key a schema could
     * have — the rest is not a preset. Empty is none.
     *
     * @return array<string, string|int|float>|null
     */
    public static function preset(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $preset = [];

        foreach ($value as $key => $one) {
            if (! is_string($key) || preg_match(Validator::KEY, $key) !== 1) {
                continue;
            }

            if (is_int($one) || is_float($one)) {
                $preset[$key] = $one;
            } elseif (is_string($one) && trim($one) !== '') {
                $preset[$key] = trim($one);
            }
        }

        ksort($preset);

        return $preset === [] ? null : $preset;
    }

    /**
     * @return array<string, string|int|float>|null
     */
    private static function decode(mixed $stored): ?array
    {
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        return is_array($decoded) ? self::preset($decoded) : null;
    }
}
