<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\HasUrl;

/**
 * The article number on the end of the slug — an address a customer recognises from the box.
 *
 * @property string $slug
 * @property string|null $sku
 */
class Product extends Model
{
    use HasUrl;

    protected $table = 'products';

    protected $fillable = ['name', 'slug', 'sku'];
}
