<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Seo\HasSeo;

/**
 * A content record with a card of its own.
 *
 * The real one is a page, an article, a product — none of which this package has or should
 * have. What `HasSeo` needs from a model is a table and a key, so the test gives it exactly
 * that and nothing else: anything more would be testing the content module instead.
 *
 * @property int $id
 * @property string|null $name
 */
final class SeoEntity extends Model
{
    use HasSeo;

    protected $table = 'seo_entities';

    protected $guarded = [];

    public $timestamps = false;
}
