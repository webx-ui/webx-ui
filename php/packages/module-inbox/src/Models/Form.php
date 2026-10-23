<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use WebxUi\Inbox\Exceptions\FormHasSubmissions;
use WebxUi\Localization\HasTranslations;

/**
 * One form of the site.
 *
 * @property int $id
 * @property string $slug
 * @property mixed $title
 * @property bool $is_enabled
 * @property array<string, mixed> $options
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Form extends Model
{
    use HasTranslations;

    /** The option that says how a submission is told about: {@see notifyVia()}. */
    public const NOTIFY_VIA = 'notify_via';

    public const VIA_EMAIL = 'email';

    public const VIA_CHANNELS = 'channels';

    protected $table = 'inbox_forms';

    protected $guarded = [];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'options' => 'array',
            'position' => 'integer',
        ];
    }

    /**
     * Every field the form has ever had, deleted ones excepted — the editor's list.
     *
     * @return HasMany<Field, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(Field::class, 'form_id')->orderBy('position')->orderBy('id');
    }

    /**
     * The fields that are actually drawn and actually accepted. Everything that speaks to a
     * visitor asks for these, never for `fields()`: a disabled field that still validated
     * would refuse a submission over something nobody was shown.
     *
     * @return HasMany<Field, $this>
     */
    public function liveFields(): HasMany
    {
        return $this->fields()->where('is_enabled', true);
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'form_id')->latest();
    }

    /**
     * One setting of §5.
     *
     * Read with a literal key and not with `Arr::get`, because the keys have dots in them:
     * `thank-you.heading` is one key, the way a field name is in `module-settings`, and a
     * dotted read would go looking for a `thank-you` array that does not exist.
     */
    public function option(string $key, mixed $default = null): mixed
    {
        $options = $this->options ?? [];

        return array_key_exists($key, $options) ? $options[$key] : $default;
    }

    /**
     * The line under the form's title (§5): in the language of the page, else the main one, else
     * whichever was written. Null when there is none — nothing is printed then, not an empty `<p>`.
     */
    public function description(): ?string
    {
        $text = $this->option('description');

        if (is_array($text)) {
            $text = $text[app()->getLocale()] ?? $text[(string) config('app.fallback_locale')] ?? reset($text);
        }

        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }

    /**
     * An antispam setting of this form, or the site's default for it.
     */
    public function antispam(string $key): mixed
    {
        $own = $this->option('antispam.'.$key);

        return $own ?? config('webx-inbox.antispam.'.$key);
    }

    /**
     * Who the notification goes to: `{ admin_id }` for somebody with an account, `{ email }`
     * for an address typed in.
     *
     * @return list<array<string, mixed>>
     */
    public function recipients(): array
    {
        $recipients = $this->option('recipients', []);

        return is_array($recipients) ? array_values(array_filter($recipients, 'is_array')) : [];
    }

    /**
     * How a submission is told about (§9): a letter to the addresses, or the channels of the
     * notifications module — one or the other, never both.
     *
     * What the form keeps, or, for a form saved before there was a choice, what it was doing: a
     * form with channels chosen was a form somebody set up to use them. The setting of the mode
     * that is not chosen stays in the options, so switching back brings it back as it was.
     */
    public function notifyVia(): string
    {
        $mode = $this->option(self::NOTIFY_VIA);

        if (in_array($mode, [self::VIA_EMAIL, self::VIA_CHANNELS], true)) {
            return $mode;
        }

        $channels = $this->option('notify_channels');

        return is_array($channels) && $channels !== [] ? self::VIA_CHANNELS : self::VIA_EMAIL;
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * A form with submissions is disabled, never deleted (§2.4). The database says the same
     * thing with a restricted foreign key; this is the half of it that can explain itself.
     */
    protected static function booted(): void
    {
        static::deleting(function (Form $form): void {
            if ($form->submissions()->exists()) {
                throw new FormHasSubmissions($form->slug);
            }
        });
    }
}
