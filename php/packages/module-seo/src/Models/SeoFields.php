<?php

declare(strict_types=1);

namespace WebxUi\Seo\Models;

use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Seo\Fields;
use WebxUi\Seo\Rendering\SeoData;

/**
 * The fields a page can be given, wherever they were written down.
 *
 * A rule for an address and the meta of one entity hold exactly the same things and turn them
 * into a `SeoData` exactly the same way — the difference between them is which page they are
 * about, and that is decided by whoever asks. Kept in one place so the two cannot drift: a
 * field added to the card has one spelling on the server, not two.
 *
 * The model using this is expected to use `HasTranslations` as well and to name
 * {@see Fields::TRANSLATED} as its translatable attributes — said in the model rather than
 * here, because `HasTranslations` declares `translatable()` too and PHP refuses two traits
 * offering one method without being told which.
 */
trait SeoFields
{
    /** What this contributes to the page, in one language. */
    public function toSeoData(?string $locale = null): SeoData
    {
        /** @var static $fields */
        $fields = $locale === null ? $this : $this->forLocale($locale);

        return SeoData::make([
            'title' => $fields->title,
            'h1' => $fields->h1,
            'description' => $fields->description,
            'keywords' => $fields->keywords,
            'canonical' => $fields->canonical,
            'robots' => $fields->robots,
            'og' => [
                'title' => $fields->og_title,
                'description' => $fields->og_description,
                'image' => $this->imageUrl($locale),
            ],
            'jsonLd' => $fields->json_ld,
        ]);
    }

    /**
     * The address of the picture, worked out by whoever owns `wx-media`.
     *
     * Asked through the field-type registry rather than through the media module directly:
     * SEO does not depend on a library being installed, and a site that stores its pictures
     * somewhere else registers its own type under the same name.
     */
    public function imageUrl(?string $locale = null): ?string
    {
        $stored = $this->og_image;

        if (! is_array($stored) || $stored === []) {
            return null;
        }

        $resolved = app(FieldTypes::class)->get('wx-media')?->resolve($stored, ['type' => 'wx-media'], $locale);
        $url = is_array($resolved) ? ($resolved['url'] ?? null) : null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * The fields as the panel edits them: every language of every text field, and the picture
     * with the address beside it so a form can show a preview without asking the library.
     *
     * Only what has been written. A record nobody has given any SEO answers with an empty
     * object rather than a shape full of nulls — that is what the card's value type says, and
     * it is what makes "has this page got SEO of its own" a question with an answer.
     *
     * @return array<string, mixed>
     */
    public function seoFieldValues(): array
    {
        $values = [];

        foreach (Fields::TRANSLATED as $field) {
            $translations = $this->getTranslations($field);

            if ($translations !== []) {
                $values[$field] = $translations;
            }
        }

        $image = $this->og_image;

        if (is_array($image) && $image !== []) {
            $values['og_image'] = $image + ['url' => $this->imageUrl()];
        }

        foreach (['canonical', 'robots'] as $field) {
            if (is_string($this->{$field}) && $this->{$field} !== '') {
                $values[$field] = $this->{$field};
            }
        }

        if (is_array($this->json_ld) && $this->json_ld !== []) {
            $values['json_ld'] = $this->json_ld;
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    protected function seoCasts(): array
    {
        return [
            'og_image' => 'array',
            'json_ld' => 'array',
        ];
    }
}
