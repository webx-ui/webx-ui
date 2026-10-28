<?php

declare(strict_types=1);

namespace WebxUi\Users\Models;

use Illuminate\Auth\MustVerifyEmail as VerifiesEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use WebxUi\Admin\Visitors\Visitor;

/**
 * A visitor of the site (spec §5): a customer, a reader of a closed section, a member of a club.
 *
 * Never an administrator: no permissions (`HasPermissions` is not implemented, and a test keeps
 * it so), no way into the panel — `cms.auth` lets in only `CmsUser`. The address is the login and
 * is kept normalised, so `Anna@Example.com ` and `anna@example.com` are one account.
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
 * @property-read string $name
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use VerifiesEmail;

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
        return Attribute::make(get: fn (): string => $this->toVisitor()->name());
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
        return new Visitor(
            id: (int) $this->getKey(),
            email: $this->email,
            verified: $this->email_verified_at !== null,
            firstName: $this->first_name,
            lastName: $this->last_name,
            phone: $this->phone,
            locale: $this->locale,
        );
    }

    /**
     * The letter of confirmation is the module's own and comes with the reset (§8); until then
     * Laravel's default letter — about a page this module does not have yet — is not sent.
     */
    public function sendEmailVerificationNotification(): void {}
}
