<?php

declare(strict_types=1);

namespace WebxUi\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use WebxUi\Localization\HasTranslations;
use WebxUi\Seo\Fields;

/**
 * What one entity says about itself.
 *
 * The same fields a rule for an address holds, attached to a record instead of to a pattern —
 * which is why the two share {@see SeoFields} rather than each spelling the merge out. One row
 * per entity: SEO is edited as a unit, and a second row would be a second answer.
 *
 * Nothing creates this row on its own. An entity that nobody has written SEO for has no row,
 * and `EntitySource` then contributes nothing — which is what lets the defaults through.
 *
 * @property int $id
 * @property string $seoable_type
 * @property int $seoable_id
 * @property mixed $title
 * @property mixed $h1
 * @property mixed $description
 * @property mixed $keywords
 * @property mixed $og_title
 * @property mixed $og_description
 * @property array<string, mixed>|null $og_image
 * @property string|null $canonical
 * @property string|null $robots
 * @property array<int|string, mixed>|null $json_ld
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeoMeta extends Model
{
    use HasTranslations;
    use SeoFields;

    protected $table = 'seo_meta';

    /** @var list<string> */
    protected $fillable = [...Fields::TRANSLATED, ...Fields::PLAIN];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return Fields::TRANSLATED;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'seoable_type', 'seoable_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return $this->seoCasts();
    }
}
