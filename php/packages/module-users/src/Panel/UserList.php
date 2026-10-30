<?php

declare(strict_types=1);

namespace WebxUi\Users\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use WebxUi\Users\Models\Identity;
use WebxUi\Users\Models\User;
use WebxUi\Users\Social\SocialAccounts;
use WebxUi\Users\UserVisitors;

/**
 * The query behind «Customers» (spec §16.2): searched by name, e-mail and phone; filtered by
 * group, confirmed or not, provider, blocked, the period of the registration; sorted by the
 * registration or the last sign-in. The groups and the links of a page come in one query each,
 * whatever the number of rows — a list of 50 asks what a list of 10 does.
 */
final class UserList
{
    /** What the list may be sorted by, newest first unless `-` says otherwise. */
    public const array SORTS = ['created_at', 'last_sign_in_at', 'email'];

    /**
     * @param  array<string, mixed>  $filters  `search`, `group`, `verified`, `provider`, `blocked`, `from`, `to`, `sort`
     * @return LengthAwarePaginator<int, User>
     */
    public function page(array $filters, int $perPage, int $page = 1): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage, page: $page);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    public function query(array $filters): Builder
    {
        $query = User::query()->with([
            'groups' => static fn (BelongsToMany $groups) => $groups->select(['users_groups.id', 'users_groups.code', 'users_groups.name'])->orderBy('users_groups.name'),
            'identities' => static fn (HasMany $identities) => $identities->select(['id', 'user_id', 'provider']),
        ]);

        $term = trim((string) ($filters['search'] ?? ''));

        if ($term !== '') {
            self::match($query, $term);
        }

        $group = $filters['group'] ?? null;

        if (is_string($group) && $group !== '') {
            $query->whereHas('groups', static fn (Builder $groups) => $groups->where('code', $group));
        }

        $verified = self::flag($filters['verified'] ?? null);

        if ($verified !== null) {
            $verified ? $query->whereNotNull('email_verified_at') : $query->whereNull('email_verified_at');
        }

        $blocked = self::flag($filters['blocked'] ?? null);

        if ($blocked !== null) {
            $blocked ? $query->whereNotNull('blocked_at') : $query->whereNull('blocked_at');
        }

        $provider = $filters['provider'] ?? null;

        if ($provider === SocialAccounts::PASSWORD) {
            $query->whereNotNull('password');
        } elseif (is_string($provider) && $provider !== '') {
            $query->whereHas('identities', static fn (Builder $links) => $links->where('provider', $provider));
        }

        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $day = $filters[$key] ?? null;

            if (is_string($day) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
                $bound = Carbon::parse($day);
                $query->where('created_at', $operator, $key === 'from' ? $bound->startOfDay() : $bound->endOfDay());
            }
        }

        $sort = (string) ($filters['sort'] ?? '-created_at');
        $column = ltrim($sort, '-');
        $column = in_array($column, self::SORTS, true) ? $column : 'created_at';
        // The newest first by default; the address reads best from A.
        $direction = str_starts_with($sort, '-') || ($sort === '' && $column !== 'email') ? 'desc' : 'asc';

        // A visitor who never signed in goes to the end whichever way the dates run.
        if ($column === 'last_sign_in_at') {
            $query->orderByRaw('last_sign_in_at is null');
        }

        return $query->orderBy($column, $direction)->orderBy('id', $direction);
    }

    /**
     * By the address and the phone as typed, by the name in either order — «Anna Petrova» and
     * «Petrova» find the same person. The phone is compared by its digits too: «+800 1234» and
     * «8001234» are one number to whoever types it. The search of other modules
     * ({@see UserVisitors::search()}) asks the same.
     *
     * @param  Builder<User>  $query
     */
    public static function match(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $words = array_values(array_filter(preg_split('/\s+/u', $term) ?: []));

        $query->where(static function (Builder $any) use ($like, $words, $term): void {
            $any->where('email', 'like', mb_strtolower($like))
                ->orWhere('phone', 'like', $like);

            $digits = preg_replace('/\D+/', '', $term) ?? '';

            if (strlen($digits) >= 3) {
                $any->orWhere('phone', 'like', '%'.implode('%', str_split($digits)).'%');
            }

            $any->orWhere(static function (Builder $name) use ($words): void {
                foreach ($words as $word) {
                    $one = '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%';
                    $name->where(static fn (Builder $either) => $either->where('first_name', 'like', $one)->orWhere('last_name', 'like', $one));
                }
            });
        });
    }

    private static function flag(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * One row of the list: what the table shows, nothing it does not — no password, no token, no
     * id at a provider. The ways are read off the links loaded with the page.
     *
     * @param  list<string>  $shown  the providers shown on the site
     * @return array<string, mixed>
     */
    public static function row(User $user, array $shown): array
    {
        $linked = $user->identities->map(static fn (Identity $identity): string => $identity->provider)->unique()->values()->all();

        return [
            'id' => (int) $user->getKey(),
            'name' => $user->first_name !== null || $user->last_name !== null ? trim($user->first_name.' '.$user->last_name) : null,
            'email' => $user->email,
            'phone' => $user->phone,
            'verified' => $user->email_verified_at !== null,
            'blocked' => $user->blocked_at !== null,
            'groups' => $user->groups->map(static fn ($group): array => ['code' => $group->code, 'name' => $group->name])->values()->all(),
            'ways' => array_values(array_unique([
                ...($user->password !== null ? [SocialAccounts::PASSWORD] : []),
                ...array_map('strval', $linked),
            ])),
            // A link to a provider switched off since is no way in (§9.6): the panel greys it.
            'ways_off' => array_values(array_diff(array_map('strval', $linked), $shown)),
            'created_at' => $user->created_at?->toAtomString(),
            'last_sign_in_at' => $user->last_sign_in_at?->toAtomString(),
        ];
    }
}
