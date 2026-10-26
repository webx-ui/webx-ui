<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Configurator\Panel\ConfiguratorWriter;
use WebxUi\Localization\HasTranslations;

/**
 * A configurator: a key, a name, the request form, and the schema document (§4, §5).
 *
 * Drafted and versioned by the frame, as a page is (decision 2): the columns are what the site
 * prices by, `draft` holds the `title`, `schema` and `form` being prepared, and every publication
 * is a version. Nothing of its own about either — the module's part is that a publication is
 * refused while the schema has problems ({@see ConfiguratorWriter}).
 *
 * @property int $id
 * @property string $key
 * @property array<string, string>|string|null $title
 * @property array<string, mixed>|null $schema
 * @property string|null $form
 * @property array<string, mixed>|null $draft
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Configurator extends Model
{
    use HasDraft;
    use HasTranslations;
    use HasVersions {
        unversionedAttributes as private contentlessAttributes;
    }
    use SoftDeletes;

    /** Never on the site. */
    public const STATUS_DRAFT = 'draft';

    /** On the site, with nothing waiting. */
    public const STATUS_PUBLISHED = 'published';

    /** On the site, with edits that are not on it yet. */
    public const STATUS_MODIFIED = 'modified';

    /** Was on the site and was taken off it. */
    public const STATUS_UNPUBLISHED = 'unpublished';

    /** What a draft holds: everything a publication changes. */
    public const DRAFTED = ['title', 'schema', 'form'];

    /** @var list<string> */
    protected $fillable = ['key', 'title', 'schema', 'form'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['schema' => 'array'];
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title'];
    }

    /**
     * @return list<string>
     */
    public function unversionedAttributes(): array
    {
        // The key is what blocks of the site name it by; rolling back must never change it.
        return [...$this->contentlessAttributes(), 'key'];
    }

    public function status(): string
    {
        if (! $this->isPublished()) {
            return $this->versions()->published()->exists() ? self::STATUS_UNPUBLISHED : self::STATUS_DRAFT;
        }

        return $this->hasDraft() ? self::STATUS_MODIFIED : self::STATUS_PUBLISHED;
    }

    /**
     * What the editor is working on: the draft where there is one, the columns where there is not.
     *
     * @return array{title: array<string, string>, schema: array<string, mixed>|null, form: string|null}
     */
    public function editing(): array
    {
        $shown = $this->hasDraft() ? $this->withDraft() : $this;
        $schema = $shown->getAttribute('schema');

        return [
            'title' => $shown->getTranslations('title'),
            'schema' => is_array($schema) ? $schema : null,
            'form' => is_string($shown->form) && $shown->form !== '' ? $shown->form : null,
        ];
    }

    /**
     * The schema the site prices by, or null while there is none on it.
     *
     * @return array<string, mixed>|null
     */
    public function liveSchema(): ?array
    {
        return $this->isPublished() && ! $this->trashed() && is_array($this->schema) ? $this->schema : null;
    }

    /** The number of the publication the site shows — what a request says it was priced by. */
    public function liveVersion(): ?int
    {
        if (! $this->isPublished()) {
            return null;
        }

        $number = $this->versions()->published()->max('number');

        return is_numeric($number) ? (int) $number : null;
    }
}
