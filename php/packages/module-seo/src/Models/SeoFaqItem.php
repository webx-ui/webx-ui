<?php

declare(strict_types=1);

namespace WebxUi\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Localization\HasTranslations;

/**
 * One question of a page's FAQ (§18.5), on the exact rule of that page.
 *
 * @property int $id
 * @property int $seo_url_id
 * @property mixed $question
 * @property mixed $answer
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeoFaqItem extends Model
{
    use HasTranslations;

    protected $table = 'seo_faq_items';

    protected $fillable = ['seo_url_id', 'question', 'answer', 'position'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['question', 'answer'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seo_url_id' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<SeoUrl, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'seo_url_id');
    }

    /** The question in this language, and only this one. */
    public function questionText(string $locale): string
    {
        $text = $this->getTranslation('question', $locale, fallback: false);

        return is_string($text) ? trim($text) : '';
    }

    /**
     * The answer as a page prints it: the document the editor wrote, with library pictures
     * pointed at where they live now — the same way `module-faq` prints its answers.
     */
    public function answerHtml(string $locale): string
    {
        $stored = $this->getTranslation('answer', $locale, fallback: false);

        if (! is_string($stored) || trim($stored) === '') {
            return '';
        }

        $type = app(FieldTypes::class)->get('wx-rich-text');
        $resolved = $type === null ? $stored : $type->resolve($stored, [], $locale);

        return is_string($resolved) ? $resolved : $stored;
    }
}
