<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Dictionaries;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\Category;
use WebxUi\Admin\Categories\IsCategory;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Models\Product;
use WebxUi\Localization\HasTranslations;

/**
 * A reference book of the catalogue with a code and a tone — labels, stock statuses (§2 of the
 * dictionaries spec): a flat list ordered by hand, a translated name, a code for the filter's
 * address and for templates, and a tone the panel and the site paint it in.
 *
 * It is the shared category code of the panel ({@see IsCategory}) with three things of its own.
 * The **code** is one for every language (`/laptops/label_sale/` on all of them), so it is a
 * column rather than the translated slug the shared code makes on create: the slug is emptied on
 * every save, and the code is made from it when none was given. The **tone** is a word, never a
 * hex: the site turns it into a class, the panel into a tag of the same tone. And an **edit** that
 * changes what products show or how they are found marks them for the engine in one query.
 *
 * `is_visible` of the shared code is "in the filter" here (`is_filterable` of the spec): a record
 * a reader cannot choose is exactly a record they cannot reach.
 *
 * Not `final`, and neither are the models: PHPStan does not take the `$this` of a final class for
 * the `$this` the trait's relation promises (docs/pitfalls/laravel-and-php.md).
 *
 * @property int $id
 * @property array<string, string>|string|null $title
 * @property string $code
 * @property string $color
 * @property bool $is_visible
 * @property int $position
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
abstract class Dictionary extends Model implements Category
{
    use HasExtra;
    use HasTranslations;
    use IsCategory;
    use SoftDeletes;

    /** The tones a record may be painted in — the tones `WxTag` has. */
    public const TONES = ['neutral', 'primary', 'success', 'warning', 'danger', 'info'];

    /** `[a-z0-9-]`, as a facet value in an address has to be. */
    public const CODE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const CODE_LENGTH = 32;

    /**
     * The products this record is on, for the engine to read again after an edit — a query, never
     * a list: a label on forty thousand products is one `insert … select`.
     *
     * @return Builder<Product>
     */
    abstract public function affectedProducts(): Builder;

    /**
     * The columns whose change changes what the products show or how they are found.
     *
     * @return list<string>
     */
    public function touchingFields(): array
    {
        return ['title', 'code', 'color', 'is_visible'];
    }

    protected static function booted(): void
    {
        static::saving(static function (Dictionary $record): void {
            if (trim((string) $record->getAttribute('code')) === '') {
                $record->setAttribute('code', $record->freeCode());
            }

            if ($record->getAttribute('color') === null) {
                $record->setAttribute('color', self::TONES[0]);
            }

            // Past the translations, which would read null as "empty in this language".
            $record->attributes['slug'] = null;
            $record->assertValid();
        });

        static::updated(static function (Dictionary $record): void {
            if ($record->wasChanged($record->touchingFields())) {
                Container::getInstance()->make(Catalog::class)->touchQuery($record->affectedProducts());
            }
        });
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'slug'];
    }

    public function extraScreen(): string
    {
        return static::categoryKind()->screen;
    }

    /**
     * @return list<string>
     */
    public function categoryFields(): array
    {
        return ['title', 'code', 'color', 'is_visible'];
    }

    public function writeCategoryValue(string $field, mixed $value): void
    {
        match ($field) {
            'code' => $this->setAttribute('code', Str::lower(trim((string) $value))),
            'color' => $this->setAttribute('color', trim((string) $value)),
            default => $this->writeOwnValue($field, $value),
        };
    }

    /**
     * Title or code containing the words — the code is what a template and an address know it by.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn (Builder $nested): Builder => $nested
            ->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
            ->orWhere($this->qualifyColumn('code'), 'like', Str::lower($like)));
    }

    /** The name in this language, the code where there is none. */
    public function displayName(string $locale): string
    {
        $title = $this->getTranslation('title', $locale);

        if (is_string($title) && trim($title) !== '') {
            return $title;
        }

        $code = $this->getAttribute('code');

        return is_string($code) && $code !== '' ? $code : '#'.$this->getKey();
    }

    /** A flag or a translated field — what the shared code writes — with flags as booleans. */
    protected function writeOwnValue(string $field, mixed $value): void
    {
        if (str_starts_with($field, 'is_')) {
            $this->setAttribute($field, (bool) $value);

            return;
        }

        if (! in_array($field, $this->translatable(), true)) {
            $this->setAttribute($field, $value);

            return;
        }

        if (is_array($value)) {
            $this->setAttribute($field, [...$this->getTranslations($field), ...$value]);

            return;
        }

        $this->setTranslation($field, app()->getLocale(), $value);
    }

    /**
     * The rules every door writes through — the form, an agent, an import — so they live in the
     * model rather than in one controller.
     *
     * @throws ValidationException
     */
    protected function assertValid(): void
    {
        $code = (string) $this->getAttribute('code');
        $errors = [];

        if (preg_match(self::CODE, $code) !== 1 || strlen($code) > self::CODE_LENGTH) {
            $errors['code'] = [(string) __('webx-catalog::errors.dictionary-code', ['max' => self::CODE_LENGTH])];
        } elseif (static::withTrashed()->where('code', $code)->whereKeyNot($this->getKey())->exists()) {
            $errors['code'] = [(string) __('webx-catalog::errors.dictionary-code-taken')];
        }

        if (! in_array($this->getAttribute('color'), self::TONES, true)) {
            $errors['color'] = [(string) __('webx-catalog::errors.dictionary-tone', ['tones' => implode(', ', self::TONES)])];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * A code nobody holds, made out of what the shared code gave a new record: its slug, else its
     * title. `sale`, then `sale-2`.
     */
    private function freeCode(): string
    {
        $base = '';

        foreach ([...array_values($this->getTranslations('slug')), ...array_values($this->getTranslations('title'))] as $candidate) {
            $base = is_string($candidate) ? Str::slug($candidate) : '';

            if ($base !== '') {
                break;
            }
        }

        $base = substr($base === '' ? 'item' : $base, 0, self::CODE_LENGTH - 4);
        $code = $base;

        for ($n = 2; static::withTrashed()->where('code', $code)->exists(); $n++) {
            $code = $base.'-'.$n;
        }

        return $code;
    }
}
