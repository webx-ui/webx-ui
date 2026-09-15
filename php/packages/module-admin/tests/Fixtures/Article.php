<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Localization\HasTranslations;

/**
 * An entity the way a content module would write one: a translatable title, a body of
 * content, a slug that is structure rather than content, a draft and a history.
 *
 * @property int $id
 * @property string $slug
 * @property string|null $title
 * @property array<string, mixed>|null $body
 * @property array<string, mixed>|null $draft
 * @property Carbon|null $published_at
 */
final class Article extends Model
{
    use HasDraft;
    use HasTranslations;
    use HasVersions;

    protected $table = 'articles';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['body' => 'array'];
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title'];
    }

    /** The slug is an address, applied at once, and so not part of a version. */
    public function routeSlugAttribute(): string
    {
        return 'slug';
    }
}
