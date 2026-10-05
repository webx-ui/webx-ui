<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Mail;

use Illuminate\Support\Collection;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Inbox\Models\Form;

/**
 * Who a form would actually write to, said in the open.
 *
 * The stored list (`options.recipients`) names people; this says which of them a letter would
 * reach today. The two differ quietly: an administrator deleted or switched off since, an
 * address typed wrong. A form that reaches nobody saves every submission and tells nobody,
 * which from the outside looks exactly like a queue that has not run yet — so the notifier,
 * the panel, MCP and the audit all read the answer from here rather than each guessing.
 *
 * The stored shape is not touched: this only reads it.
 */
final class Recipients
{
    /** The administrator named is no longer there. */
    public const ADMIN_DELETED = 'admin_deleted';

    /** The administrator named is switched off. */
    public const ADMIN_INACTIVE = 'admin_inactive';

    /** What was typed is not an e-mail address. */
    public const INVALID_EMAIL = 'invalid_email';

    /**
     * Where the administrators named by a form are kept once looked up: a relation set by
     * hand, never saved, so a list of forms costs one query rather than one per form.
     */
    private const LOADED = 'recipientAdmins';

    /**
     * Look up every administrator the forms name, in one query.
     *
     * @param  iterable<Form>  $forms
     */
    public static function load(iterable $forms): void
    {
        $forms = Collection::make($forms);
        $ids = $forms->flatMap(static fn (Form $form): array => self::adminIds($form))->unique()->values();

        $admins = $ids->isEmpty()
            ? new Collection
            : CmsUser::query()->whereIn('id', $ids->all())->get()->keyBy(static fn (CmsUser $admin): int => (int) $admin->getKey())->toBase();

        foreach ($forms as $form) {
            $form->setRelation(self::LOADED, $admins->only(self::adminIds($form)));
        }
    }

    /**
     * Every recipient the form names, each with whether a letter would reach it and, if not,
     * why not.
     *
     * @return list<array{type: 'admin'|'email', admin_id?: int, name?: string|null, email: string|null, receives: bool, problem: string|null}>
     */
    public static function describe(Form $form): array
    {
        return array_map(static function (array $one): array {
            unset($one['admin']);

            return $one;
        }, self::resolve($form));
    }

    /** Whether a submission of this form would write to anybody at all. */
    public static function notifies(Form $form): bool
    {
        foreach (self::resolve($form) as $one) {
            if ($one['receives']) {
                return true;
            }
        }

        return false;
    }

    /**
     * The same list with the account beside each administrator, for the notifier, which needs
     * the language they keep the panel in.
     *
     * @return list<array{type: 'admin'|'email', admin_id?: int, name?: string|null, email: string|null, receives: bool, problem: string|null, admin?: CmsUser|null}>
     */
    public static function resolve(Form $form): array
    {
        $admins = self::admins($form);
        $resolved = [];

        foreach ($form->recipients() as $recipient) {
            if (isset($recipient['admin_id'])) {
                $id = (int) $recipient['admin_id'];
                $admin = $admins->get($id);

                $resolved[] = [
                    'type' => 'admin',
                    'admin_id' => $id,
                    'name' => $admin?->name,
                    'email' => $admin?->email,
                    'receives' => $admin !== null && $admin->is_active,
                    'problem' => match (true) {
                        $admin === null => self::ADMIN_DELETED,
                        ! $admin->is_active => self::ADMIN_INACTIVE,
                        default => null,
                    },
                    'admin' => $admin,
                ];

                continue;
            }

            $email = $recipient['email'] ?? null;
            $valid = is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

            $resolved[] = [
                'type' => 'email',
                'email' => is_string($email) ? $email : null,
                'receives' => $valid,
                'problem' => $valid ? null : self::INVALID_EMAIL,
            ];
        }

        return $resolved;
    }

    /**
     * @return Collection<int, CmsUser>
     */
    private static function admins(Form $form): Collection
    {
        if ($form->relationLoaded(self::LOADED)) {
            /** @var Collection<int, CmsUser> */
            return $form->getRelation(self::LOADED);
        }

        $ids = self::adminIds($form);

        return $ids === []
            ? new Collection
            : CmsUser::query()->whereIn('id', $ids)->get()->keyBy(static fn (CmsUser $admin): int => (int) $admin->getKey())->toBase();
    }

    /**
     * @return list<int>
     */
    private static function adminIds(Form $form): array
    {
        $ids = [];

        foreach ($form->recipients() as $recipient) {
            if (isset($recipient['admin_id'])) {
                $ids[] = (int) $recipient['admin_id'];
            }
        }

        return $ids;
    }
}
