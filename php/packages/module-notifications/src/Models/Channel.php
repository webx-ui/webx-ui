<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Localization\HasTranslations;
use WebxUi\Notifications\Sending\Credentials;

/**
 * Where and how to notify, as an administrator set it up (§3 of the notifications spec).
 *
 * `credentials` is encrypted with the application key and hidden from every serialisation; the
 * journal writes it as a mask (decision 3). The template and the subject hold a text per language
 * (decision 13): a channel needs one, and a message in a language it lacks falls back to the
 * site's main language, then to any it has.
 *
 * Off until somebody switches it on: a new channel and a copy send nothing but tests.
 *
 * @property int $id
 * @property string $name
 * @property string $provider
 * @property string|null $event
 * @property array<string, string>|null $credentials
 * @property array<string, mixed>|null $options
 * @property list<array<string, mixed>>|null $recipients
 * @property bool $enabled
 * @property int|null $copied_from
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Channel extends Model
{
    use HasTranslations;
    use RecordsHistory;
    use SoftDeletes;

    /** The type its rows have in the journal. */
    public const TYPE = 'notifications';

    /** The screen a channel is edited on. */
    public const SCREEN = 'notifications.channel';

    protected $table = 'notifications_channels';

    /** @var list<string> */
    protected $fillable = ['name', 'provider', 'event', 'credentials', 'options', 'recipients', 'subject', 'template', 'enabled', 'copied_from'];

    /** @var list<string> */
    protected $hidden = ['credentials'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'enabled' => false,
    ];

    /** @var array<string, string> */
    protected $casts = [
        'credentials' => 'encrypted:array',
        'options' => 'array',
        'recipients' => 'array',
        'enabled' => 'boolean',
        'copied_from' => 'integer',
    ];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['subject', 'template'];
    }

    /**
     * The secrets change in the journal as a mask: that the token was replaced is worth knowing,
     * the token is not.
     *
     * @return list<string>
     */
    public function historyMasked(): array
    {
        return ['credentials'];
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * The secrets, decrypted, for one send or one check. Empty when none are set — and when they
     * no longer decrypt, which {@see readable()} tells apart.
     */
    public function secrets(): Credentials
    {
        return new Credentials(array_filter(array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            $this->decrypted() ?? [],
        ), static fn (string $value): bool => $value !== ''));
    }

    /**
     * Which secrets are set — the only thing about them anybody is told.
     *
     * @return list<string>
     */
    public function credentialsSet(): array
    {
        return array_keys($this->secrets()->names());
    }

    /** False when the secrets were written with another application key (APP_KEY changed). */
    public function readable(): bool
    {
        return $this->decrypted() !== null;
    }

    /**
     * The secrets as stored, decrypted the way the cast does it — but asked, not trusted: a value
     * written under another key is null here rather than an exception on every read.
     *
     * @return array<array-key, mixed>|null
     */
    private function decrypted(): ?array
    {
        $raw = $this->getAttributes()['credentials'] ?? null;

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $json = static::currentEncrypter()->decrypt($raw, false);
        } catch (DecryptException) {
            return null;
        }

        $values = is_string($json) ? json_decode($json, true) : null;

        return is_array($values) ? $values : [];
    }

    /**
     * The template in a language, or in the next one it has: the site's main language, then the
     * first one written.
     */
    public function templateIn(?string $locale): string
    {
        return $this->textIn('template', $locale) ?? '';
    }

    public function subjectIn(?string $locale): ?string
    {
        return $this->textIn('subject', $locale);
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recipientList(): array
    {
        return array_values($this->recipients ?? []);
    }

    private function textIn(string $field, ?string $locale): ?string
    {
        $value = $this->getTranslation($field, $locale);

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        foreach ($this->getTranslations($field) as $text) {
            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
        }

        return null;
    }
}
