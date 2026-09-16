<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The glued styles and scripts of one set of block types, at one version each.
 *
 * A table rather than a cache, and that is the point: a cache flushed on production must not
 * turn into a page without styles. Written the first time a set is rendered together, served
 * by `/blocks/{hash}.css` and `.js` forever — the hash names the versions, so the content never
 * changes under it.
 *
 * @property string $hash
 * @property list<array{0: string, 1: int}> $types Pairs of slug and version, in the order glued.
 * @property string $css
 * @property string|null $js
 * @property Carbon|null $created_at
 */
class BlockBundle extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'block_bundles';

    protected $primaryKey = 'hash';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['hash', 'types', 'css', 'js'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'types' => 'array',
        ];
    }

    /**
     * The versions this bundle was glued from, slug → number.
     *
     * @return array<string, int>
     */
    public function versions(): array
    {
        $versions = [];

        foreach ($this->types ?? [] as $pair) {
            if (is_array($pair) && isset($pair[0], $pair[1])) {
                $versions[(string) $pair[0]] = (int) $pair[1];
            }
        }

        return $versions;
    }
}
