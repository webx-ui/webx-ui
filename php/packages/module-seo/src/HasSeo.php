<?php

declare(strict_types=1);

namespace WebxUi\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use WebxUi\Seo\Models\SeoMeta;
use WebxUi\Seo\Rendering\SeoData;

/**
 * An entity that can say things about itself.
 *
 * Everything the trait needs is in `seo_meta`, so a content module gets the whole card by
 * writing one `use` and nothing else: the row is created when somebody fills the card in,
 * removed when they empty it, and read by `EntitySource` while a page is being rendered.
 *
 *     class Page extends Model
 *     {
 *         use HasSeo;
 *     }
 *
 * What it deliberately does not do is take part in drafts and versions. SEO is saved the moment
 * it is saved, on a page that is published and on one that is not — a description that only
 * reaches search engines at the next publication is the kind of thing an editor discovers from
 * a search engine.
 *
 * @phpstan-require-extends Model
 */
trait HasSeo
{
    /**
     * @return MorphOne<SeoMeta, $this>
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable', 'seoable_type', 'seoable_id');
    }

    /**
     * What this entity contributes to its page, or null when nobody has written anything.
     *
     * Null rather than an empty object on purpose: a source that answers is a source that took
     * part in the merge, and "this page has SEO of its own" has to stay a question with an
     * answer.
     */
    public function seoData(?string $locale = null): ?SeoData
    {
        $meta = $this->seoMeta();

        if ($meta === null) {
            return null;
        }

        $data = $meta->toSeoData($locale);

        return $data->isEmpty() ? null : $data;
    }

    /**
     * The card's value, as the panel edits it: every language of every text field.
     *
     * @return array<string, mixed>
     */
    public function seoValue(): array
    {
        return $this->seoMeta()?->seoFieldValues() ?? [];
    }

    /**
     * Write the card back.
     *
     * An emptied card removes the row rather than keeping one full of nulls: the difference
     * between "nothing written here" and "everything written here is blank" is the difference
     * between the defaults reaching the page and not.
     *
     * @param  array<string, mixed>|null  $value  What `wx-seo` stored, already checked and cast.
     */
    public function saveSeo(?array $value): void
    {
        $meta = $this->seo()->first();

        if ($value === null || $value === []) {
            $meta?->delete();
            $this->unsetRelation('seo');

            return;
        }

        if ($meta instanceof SeoMeta) {
            // Every key of the card travels together, so the whole record is replaced rather
            // than merged — a field the editor emptied has to come back empty.
            $meta->forceFill(array_fill_keys([...Fields::TRANSLATED, ...Fields::PLAIN], null));
            $meta->fill($value)->save();
        } else {
            $this->seo()->create($value);
        }

        $this->unsetRelation('seo');
    }

    private function seoMeta(): ?SeoMeta
    {
        $meta = $this->relationLoaded('seo') ? $this->getRelation('seo') : $this->seo()->first();

        return $meta instanceof SeoMeta ? $meta : null;
    }

    /**
     * The meta goes with the entity, but only when the entity really goes: a page in the bin
     * is a page that can come back, and coming back without its description is a bug reported
     * as "the page lost its SEO".
     */
    protected static function bootHasSeo(): void
    {
        // `registerModelEvent` rather than `observe()`: building an observer's model while this
        // one is still booting is refused by Eloquent outright (CLAUDE.md §4).
        static::registerModelEvent('deleted', static function (Model $model): void {
            $softDeleted = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();

            if (! $softDeleted && method_exists($model, 'seo')) {
                $model->seo()->delete();
            }
        });
    }
}
