<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Seo\HasSeo;

/**
 * A content record that keeps `updated_at`, which is what every real one does: the card's own
 * table must not hide its writes from whoever reads that column.
 *
 * @property int $id
 * @property string|null $name
 */
final class StampedSeoEntity extends Model
{
    use HasSeo;

    protected $table = 'seo_stamped_entities';

    protected $guarded = [];
}
