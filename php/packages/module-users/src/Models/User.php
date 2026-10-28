<?php

declare(strict_types=1);

namespace WebxUi\Users\Models;

use Illuminate\Auth\MustVerifyEmail as VerifiesEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Admin\Screens\HasExtra;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Users\Emails\Verification;
use WebxUi\Users\Fields\UserFields;

/**
 * A visitor of the site (spec §5): a customer, a reader of a closed section, a member of a club.
 *
 * Never an administrator: no permissions (`HasPermissions` is not implemented, and a test keeps
 * it so), no way into the panel — `cms.auth` lets in only `CmsUser`. The address is the login and
 * is kept normalised, so `Anna@Example.com ` and `anna@example.com` are one account. The fields of
 * the site (spec §10) are in `extra`: `$user->extra('club-card')`.
 *
 * @property int $id
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property string|null $locale
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $blocked_at
 * @property Carbon|null $last_sign_in_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $name
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasExtra {
        extra as screenExtra;
    }
    use RecordsHistory;
    use VerifiesEmail;

    /** The panel's card of a visitor: a field a site patched onto it directly is kept in `extra` too. */
    public const string SCREEN = 'users.form';

    /** The journal's type (§16): every change of a visitor, from the panel, an agent or the visitor. */
    public const string TYPE = 'users.user';

    protected $table = 'users';

    protected $fillable = ['email', 'password', 'first_name', 'last_name', 'phone', 'locale', 'extra'];

    protected $hidden = ['password', 'remember_token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'blocked_at' => 'datetime',
            'last_sign_in_at' => 'datetime',
            'password' => 'hashed',
            'extra' => 'array',
        ];
    }

    /**
     * A remember token from the start: it is the stamp of every session (TrackVisitor), and one
     * set later by the first «Remember me» would end the sessions already open.
     */
    protected static function booted(): void
    {
        static::creating(static function (self $user): void {
            if ($user->getRememberToken() === null || $user->getRememberToken() === '') {
                $user->setRememberToken(Str::random(60));
            }
        });
    }

    public function extraScreen(): string
    {
        return self::SCREEN;
    }

    /**
     * What the site prints for one of its fields: read through the field's type, as a field of the
     * project is (§10.3). A field declared to `UserFields` is read by its own node — the panel's
     * screen need not be there for it; anything else as `HasExtra` reads it.
     */
    public function extra(string $name, ?string $locale = null): mixed
    {
        $field = app(UserFields::class)->get($name);

        return $field === null
            ? $this->screenExtra($name, $locale)
            : app(ScreenValues::class)->resolve($field->node(), $this->extraRaw($name), $locale);
    }

    public static function normaliseEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::make(set: static fn (string $value): string => self::normaliseEmail($value));
    }

    /** @return Attribute<string|null, string|null> */
    protected function phone(): Attribute
    {
        return Attribute::make(set: static fn (?string $value): ?string => ($value = trim((string) $value)) === '' ? null : $value);
    }

    /**
     * First and last name, or the part of the address before «@» for somebody who gave none.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->visitor([])->name());
    }

    /**
     * The social networks linked to the visitor (§9.6).
     *
     * @return HasMany<Identity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(Identity::class);
    }

    /**
     * The customer groups the visitor is in (§13.1): a module adds one with `attach()`.
     *
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'users_group_user', 'user_id', 'group_id')->withPivot('created_at');
    }

    /**
     * The fields the journal leaves out: a sign-in is not a change of the visitor, and the stamp
     * of the sessions is a secret that is replaced, not edited.
     *
     * @return list<string>
     */
    public function historySkipped(): array
    {
        return ['last_sign_in_at', 'remember_token'];
    }

    /**
     * A new password is written as «changed» and never as a hash.
     *
     * @return list<string>
     */
    public function historyMasked(): array
    {
        return ['password'];
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /** @param  Builder<self>  $query */
    public function scopeByEmail(Builder $query, string $email): void
    {
        $query->where('email', self::normaliseEmail($email));
    }

    /** What the rest of the site knows of a visitor, through the seam of the panel (spec §4). */
    public function toVisitor(): Visitor
    {
        // One query a visitor, the first time; a list that loaded the groups for every row asks none.
        return $this->visitor($this->groups->pluck('code')->map(static fn (mixed $code): string => (string) $code)->values()->all());
    }

    /** @param  list<string>  $groups */
    private function visitor(array $groups): Visitor
    {
        return new Visitor(
            id: (int) $this->getKey(),
            email: $this->email,
            verified: $this->email_verified_at !== null,
            firstName: $this->first_name,
            lastName: $this->last_name,
            phone: $this->phone,
            locale: $this->locale,
            groups: $groups,
        );
    }

    /** The module's own letter (§8.1), not Laravel's: its link is a page of this module. */
    public function sendEmailVerificationNotification(): void
    {
        app(Verification::class)->send($this);
    }
}
