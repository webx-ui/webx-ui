<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Seo\Contracts\HasSeoFallback;
use WebxUi\Seo\HasSeo;
use WebxUi\Seo\Rendering\SeoData;

/**
 * A record with a card and a name of its own: a recipe, an outlet — what every content module
 * has and this package does not.
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $lead
 * @property string|null $picture
 */
final class FallbackEntity extends Model implements HasSeoFallback
{
    use HasSeo;

    protected $table = 'seo_entities';

    protected $guarded = [];

    public $timestamps = false;

    public function seoFallback(?string $locale = null): SeoData
    {
        return SeoData::fallback($this->name, $this->lead, $this->picture);
    }
}
