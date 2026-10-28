<?php

declare(strict_types=1);

namespace WebxUi\Users\Demo;

use Illuminate\Support\Carbon;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Users\Models\Group;
use WebxUi\Users\Models\Identity;
use WebxUi\Users\Models\User;

/**
 * Two groups and five visitors (spec §18), so that the list of «Customers» has its filters to
 * show: a group each and both at once, a confirmed address and one that is not, a phone and none,
 * one visitor «signed in with Google».
 *
 * Nobody has a password: a demo visitor is looked at, not signed in as — whoever checks the site
 * registers or asks for a reset link, which lands in the mail log. The Google link has a made-up
 * id, and without the keys of a real console there is no button to use it with. No field of the
 * site is declared here: that is the guide's example, not the demo's.
 */
final class UsersDemo
{
    /** @var array<string, string> code => name */
    private const GROUPS = [
        'wholesale' => 'Wholesale',
        'club' => 'Club',
    ];

    /**
     * @var list<array{first_name: string, last_name: string, email: string, phone: ?string, verified: bool, groups: list<string>, google?: string}>
     */
    private const VISITORS = [
        ['first_name' => 'Anna', 'last_name' => 'Lane', 'email' => 'anna.demo@example.com', 'phone' => '+800 1234 5678', 'verified' => true, 'groups' => ['club']],
        ['first_name' => 'Mark', 'last_name' => 'Stone', 'email' => 'mark.demo@example.com', 'phone' => '+800 1234 5679', 'verified' => true, 'groups' => ['wholesale']],
        ['first_name' => 'Lena', 'last_name' => 'Fox', 'email' => 'lena.demo@example.com', 'phone' => '+800 1234 5680', 'verified' => true, 'groups' => ['wholesale', 'club']],
        ['first_name' => 'Omar', 'last_name' => 'Hale', 'email' => 'omar.demo@example.com', 'phone' => null, 'verified' => false, 'groups' => []],
        ['first_name' => 'Iris', 'last_name' => 'Brook', 'email' => 'iris.demo@example.com', 'phone' => '+800 1234 5681', 'verified' => true, 'groups' => ['club'], 'google' => 'demo-100000000000000000001'],
    ];

    /** @return list<string> */
    public function requires(): array
    {
        return [];
    }

    public function seed(DemoLedger $ledger): void
    {
        // Visitors or groups already there are somebody's customers: no made-up ones among them.
        if (User::query()->exists() || Group::query()->exists()) {
            $ledger->note('The site already has visitors or groups of customers; the demo left them alone.');

            return;
        }

        /** @var array<string, Group> $groups */
        $groups = [];

        foreach (self::GROUPS as $code => $name) {
            $groups[$code] = Group::query()->create(['code' => $code, 'name' => $name]);
            $ledger->created($groups[$code], $name);
        }

        foreach (self::VISITORS as $index => $input) {
            $user = new User([
                'email' => $input['email'],
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'phone' => $input['phone'],
            ]);
            // Days apart, so that the sort by registration and by the last sign-in has something to do.
            $registered = Carbon::now()->subDays(30 - $index * 6);
            $user->forceFill([
                'email_verified_at' => $input['verified'] ? $registered : null,
                'last_sign_in_at' => $input['verified'] ? $registered->copy()->addDays(2) : null,
                'created_at' => $registered,
            ])->save();
            $ledger->created($user, $input['email']);

            foreach ($input['groups'] as $code) {
                $user->groups()->attach($groups[$code]->getKey(), ['created_at' => $registered]);
            }

            if (isset($input['google'])) {
                $identity = Identity::query()->create([
                    'user_id' => $user->getKey(),
                    'provider' => 'google',
                    'provider_user_id' => $input['google'],
                    'email' => $input['email'],
                    'last_used_at' => $registered->copy()->addDays(2),
                ]);
                $ledger->created($identity, 'Google of '.$input['email']);
            }
        }
    }
}
