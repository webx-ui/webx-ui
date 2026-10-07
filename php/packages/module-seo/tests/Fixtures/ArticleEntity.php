<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Seo\Contracts\HasOpenGraph;
use WebxUi\Seo\Contracts\HasSeoFallback;
use WebxUi\Seo\HasSeo;
use WebxUi\Seo\Rendering\SeoData;

/**
 * An entity that is an article to a social network: a type, dates, a section, tags — what
 * `module-blog`'s article hands over, without the blog.
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $lead
 * @property string|null $picture
 */
final class ArticleEntity extends Model implements HasOpenGraph, HasSeoFallback
{
    use HasSeo;

    protected $table = 'seo_entities';

    protected $guarded = [];

    public $timestamps = false;

    public function seoFallback(?string $locale = null): SeoData
    {
        return SeoData::fallback($this->name, $this->lead, $this->picture, 'A plate of cookies');
    }

    public function openGraphType(): string
    {
        return 'article';
    }

    public function openGraphProperties(string $locale): array
    {
        return [
            'article:published_time' => '2026-10-01T09:00:00+00:00',
            'article:modified_time' => '2026-10-05T12:30:00+00:00',
            'article:section' => 'Sweet things',
            'article:tag' => ['Baking', '', 'Gluten-free'],
            'article:author' => null,
        ];
    }
}
