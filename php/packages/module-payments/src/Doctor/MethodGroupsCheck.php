<?php

declare(strict_types=1);

namespace WebxUi\Payments\Doctor;

use Illuminate\Support\Facades\Schema;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Payments\Contracts\DeclaresGroup;
use WebxUi\Payments\MethodGroups;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentMethod;

/**
 * What the config of the groups left behind in `webx:doctor` (groups spec §2.1, §2.3, §2.7): a move
 * to a group the site no longer has, a method whose type's group is gone, a type declaring a group
 * nobody listed, a pictogram outside the set. A method never gets lost over any of these, so the
 * buyer sees nothing wrong and only this says so.
 *
 * A site with no groups at all asked for a flat list: there is nothing to say then.
 *
 * Registered only where the panel's frame is installed: payments do not need it.
 */
final class MethodGroupsCheck implements Check
{
    private const string SUBJECT = 'Payment groups';

    public function __construct(
        private readonly MethodGroups $groups,
        private readonly MethodTypes $types,
    ) {}

    public function run(): array
    {
        if ($this->groups->keys() === []) {
            return [];
        }

        // Asked, not caught: sqlite takes an unknown column in a select for a string and answers.
        if (! Schema::hasColumn('payment_methods', 'group_key')) {
            return [Diagnosis::warn(self::SUBJECT, 'The column payment_methods.group_key is not there: run php artisan migrate.')];
        }

        $found = [...$this->pictograms(), ...$this->declared()];

        foreach (PaymentMethod::query()->orderBy('position')->orderBy('id')->get(['key', 'type', 'enabled', 'group_key']) as $method) {
            if ($method->group_key !== null) {
                if (! $this->groups->has($method->group_key)) {
                    $found[] = Diagnosis::warn(self::SUBJECT, "Method {$method->key} is moved to the group {$method->group_key}, which webx-payments.groups does not list: it stands in the group of its type. Put the group back in the config or move the method.");
                }

                continue;
            }

            if ($method->enabled && $this->types->has($method->type)) {
                $wanted = $this->groups->wanted($this->types->get($method->type));

                if (! $this->groups->has($wanted)) {
                    $found[] = Diagnosis::warn(self::SUBJECT, "Method {$method->key} belongs to the group {$wanted}, which webx-payments.groups does not list: it stands in the first group. Add the group to the config or move the method.");
                }
            }
        }

        return $found;
    }

    /** @return list<Diagnosis> */
    private function pictograms(): array
    {
        $found = [];

        foreach ($this->groups->configured() as $key => $group) {
            if ($group['pictogram'] !== null && ! in_array($group['pictogram'], MethodGroups::PICTOGRAMS, true)) {
                $found[] = Diagnosis::warn(self::SUBJECT, "The group {$key} of webx-payments.groups names the pictogram {$group['pictogram']}, which is not one of ".implode(', ', MethodGroups::PICTOGRAMS).': the group goes without one.');
            }
        }

        return $found;
    }

    /** @return list<Diagnosis> */
    private function declared(): array
    {
        $found = [];

        foreach ($this->types->all() as $type) {
            if ($type instanceof DeclaresGroup && ! $this->groups->has($type->defaultGroup())) {
                $found[] = Diagnosis::warn(self::SUBJECT, "Type {$type->key()} declares the group {$type->defaultGroup()}, which webx-payments.groups does not list: its methods are placed by their kind.");
            }
        }

        return $found;
    }
}
