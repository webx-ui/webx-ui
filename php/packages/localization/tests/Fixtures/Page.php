<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Localization\HasTranslations;

/**
 * @property mixed $title
 * @property mixed $body
 * @property string|null $code
 */
class Page extends Model
{
    use HasTranslations;

    protected $guarded = [];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'body'];
    }
}
