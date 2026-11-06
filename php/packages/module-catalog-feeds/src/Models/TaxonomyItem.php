<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One category of a marketplace: the id it goes to the file by, and the path a person reads.
 *
 * @property int $id
 * @property int $taxonomy_id
 * @property string $external_id
 * @property string $path
 */
class TaxonomyItem extends Model
{
    public $timestamps = false;

    protected $table = 'catalog_feed_taxonomy_items';

    /** @var list<string> */
    protected $fillable = ['taxonomy_id', 'external_id', 'path'];
}
