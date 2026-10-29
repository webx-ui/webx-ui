<?php

declare(strict_types=1);

namespace WebxUi\Orders\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Tool;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\OrderSettings;
use WebxUi\Orders\OrderStatuses;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Orders\Panel\SettingsScreen;
use WebxUi\Orders\Panel\StatusBoard;
use WebxUi\Orders\Panel\StatusChoices;
use WebxUi\Orders\StatusColor;
use WebxUi\Orders\StatusPlan;
use WebxUi\Orders\StatusRefused;

/**
 * The statuses and the settings of the section for an agent (spec §5.6, §11.4, §12), behind
 * `orders.statuses` as the screens are.
 *
 * The list is the screen's own board ({@see StatusBoard}), and every change is a call of
 * `OrderStatuses` whose dry run — or refusal — is said in the lines the panel's dialogs show: the
 * agent and the screen answer the same question the same way. A change the service has no dry run
 * for (a public status written or brought back) is rehearsed and rolled back.
 */
final readonly class StatusTools
{
    use AnswersAgents;

    public function __construct(
        private OrderStatuses $statuses,
        private StatusBoard $board,
        private OrderSettings $settings,
        private ScreenValues $values,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $id = ['type' => 'integer', 'description' => 'The internal status\'s id (orders_statuses_list).'];
        $code = ['type' => 'string', 'description' => 'The public status\'s code: `shipped`.'];
        $words = static fn (string $what): array => ['type' => 'object', 'description' => "{$what} by language code: { \"en\": …, \"ru\": … }.", 'additionalProperties' => ['type' => 'string']];

        return Arguments::strictAll([
            Tool::read(
                'statuses_list',
                'Both levels of the statuses as the screen shows them: the public groups the buyer sees, in their order, '
                .'each with its internal statuses — names in every language, colour, the milestone it sets, active or not, '
                .'and the orders standing in each; then the deleted ones, to bring back; the statuses new orders come into '
                .'(`initial`), the palette and the milestones a status may set.',
                fn (): array => $this->board->describe(app()->getLocale()),
                permission: [OrdersModule::VIEW, OrdersModule::MANAGE, OrdersModule::REFUND, OrdersModule::STATUSES],
            ),

            Tool::mutating(
                'statuses_save',
                'Create an internal status (without `id`) at the end of its public group, or change one: the names, the public '
                .'group (`public_code`), the colour, the milestone it sets, whether it is offered. What is not sent stays. '
                .'Moving it to another group shows its orders\' buyers another public status at once, without a message; '
                .'switching it off is held like deleting it. `dry_run` answers the consequences the screen\'s dialog shows.',
                fn (array $arguments): array => $this->save($arguments),
                ['properties' => [
                    'id' => $id,
                    'public_code' => $code,
                    'name' => $words('The name'),
                    'color' => ['type' => 'string', 'enum' => array_map(static fn (StatusColor $color): string => $color->value, StatusColor::cases())],
                    'milestone' => ['type' => ['string', 'null'], 'enum' => [...array_map(static fn (Milestone $milestone): string => $milestone->value, Milestone::settable()), null], 'description' => 'The milestone moving into it sets; null — none.'],
                    'active' => ['type' => 'boolean', 'description' => 'Offered in the choice of a status.'],
                ]],
                permission: OrdersModule::STATUSES,
            ),

            Tool::mutating(
                'statuses_delete',
                'Delete an internal status softly: its orders stay in it, their buyers keep seeing its group, no order comes '
                .'into it any more. Refused for the status new orders come into and for the last active one of a group with '
                .'orders. `dry_run` answers the consequences and the orders in it, or why it is refused.',
                fn (array $arguments): array => $this->remove($arguments),
                ['properties' => ['id' => $id], 'required' => ['id']],
                permission: OrdersModule::STATUSES,
            ),

            Tool::mutating(
                'statuses_restore',
                'Bring a deleted internal status back as it was. Its public group must be alive: restore that first, or bind '
                .'the status to another group with orders_statuses_save.',
                fn (array $arguments): array => $this->restore($arguments),
                ['properties' => ['id' => $id], 'required' => ['id']],
                permission: OrdersModule::STATUSES,
            ),

            Tool::mutating(
                'statuses_reorder',
                'The order of the internal statuses inside a public group: `ids` first to last; a status of the group left out '
                .'keeps its turn after them. A status of another group named here moves into this one — `dry_run` says whose '
                .'buyers would see another public status.',
                fn (array $arguments): array => $this->reorder($arguments),
                ['properties' => [
                    'public_code' => $code,
                    'ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                ], 'required' => ['public_code', 'ids']],
                permission: OrdersModule::STATUSES,
            ),

            Tool::mutating(
                'public_statuses_save',
                'Create a public status by a new `code` at the end, or change the names and the description of the one with '
                .'this code. What is not sent stays; a language sent empty is removed.',
                fn (array $arguments): array => $this->savePublic($arguments),
                ['properties' => [
                    'code' => ['type' => 'string', 'description' => 'Lowercase Latin letters, digits and `_`, starting with a letter: what templates say.'],
                    'name' => $words('The name the buyer sees'),
                    'description' => $words('A line under it'),
                ], 'required' => ['code']],
                permission: OrdersModule::STATUSES,
            ),

            Tool::mutating(
                'public_statuses_delete',
                'Delete a public status softly. Refused while internal statuses live in it — the answer lists them — unless '
                .'`move_to` names the public status they go to first. `dry_run` answers what would move and how many orders '
                .'stand there.',
                fn (array $arguments): array => $this->removePublic($arguments),
                ['properties' => [
                    'code' => $code,
                    'move_to' => ['type' => 'string', 'description' => 'The code of the public status its internal ones move to.'],
                ], 'required' => ['code']],
                permission: OrdersModule::STATUSES,
            ),

            Tool::mutating(
                'public_statuses_restore',
                'Bring a deleted public status back as it was — its name, its place.',
                fn (array $arguments): array => $this->restorePublic($arguments),
                ['properties' => ['code' => $code], 'required' => ['code']],
                permission: OrdersModule::STATUSES,
            ),

            Tool::mutating(
                'public_statuses_reorder',
                'The order of the public statuses: `codes` first to last; one left out keeps its turn after them.',
                fn (array $arguments): array => $this->reorderPublic($arguments),
                ['properties' => ['codes' => ['type' => 'array', 'items' => ['type' => 'string']]], 'required' => ['codes']],
                permission: OrdersModule::STATUSES,
            ),

            Tool::read(
                'settings_get',
                'The settings of the section: the number\'s prefix, start and padding with the trial number of the next order, '
                .'and the statuses a new order comes into — with and without awaiting payment (null — the first ones of the '
                .'set). `stored` false — the site keeps no settings, and its config decides.',
                fn (): array => $this->settings->describe(),
                permission: OrdersModule::STATUSES,
            ),

            Tool::mutating(
                'settings_set',
                'Change the settings: `values` of the keys settings_get answers; the others stay. A format whose next number '
                .'an order already has is refused, and so is a status that is switched off or deleted. `dry_run` checks them '
                .'and answers the trial number.',
                fn (array $arguments, ?Authenticatable $caller = null): array => $this->settingsSet($arguments, $caller),
                ['properties' => ['values' => ['type' => 'object']], 'required' => ['values']],
                permission: OrdersModule::STATUSES,
            ),
        ], 'orders_');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function save(array $arguments): array
    {
        $status = isset($arguments['id']) ? $this->internal($arguments['id']) : null;
        $input = [
            'public_status_id' => isset($arguments['public_code'])
                ? $this->group($arguments['public_code'])->id
                : ($status->public_status_id ?? throw new ToolFailure('`public_code` names the public status a new one stands in.')),
            'name' => [...($status?->getTranslations('name') ?? []), ...$this->words($arguments['name'] ?? [], 'name')],
            'color' => $arguments['color'] ?? $status?->color->value ?? StatusColor::Neutral->value,
            'sets_milestone' => array_key_exists('milestone', $arguments) ? $arguments['milestone'] : $status?->sets_milestone?->value,
            'is_active' => $arguments['active'] ?? $status->is_active ?? true,
        ];

        $plan = $this->planned('save', $this->dryRun($arguments), fn (bool $dryRun): StatusPlan => $this->statuses->write($input, $status, $dryRun));

        return [
            ...$plan['said'],
            'status' => $plan['id'] !== null ? (new StatusChoices)->find($plan['id'], app()->getLocale()) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function remove(array $arguments): array
    {
        $status = $this->internal($arguments['id'] ?? null);

        if ($status->trashed()) {
            throw new ToolFailure("Status {$status->id} is deleted already. orders_statuses_restore brings it back.");
        }

        return $this->planned('delete', $this->dryRun($arguments), fn (bool $dryRun): StatusPlan => $this->statuses->remove($status, $dryRun))['said'];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function restore(array $arguments): array
    {
        $status = $this->internal($arguments['id'] ?? null);
        $work = fn (): StatusPlan => $this->statuses->restore($status);
        $dryRun = $this->dryRun($arguments);
        $said = $this->planned('restore', false, fn (): StatusPlan => $dryRun ? Rehearsal::run($work, $status->getConnectionName()) : $work())['said'];

        return [...$said, 'status' => (new StatusChoices)->find($status->id, app()->getLocale()), 'dry_run' => $dryRun];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $group = $this->group($arguments['public_code'] ?? null);

        if ($group->trashed()) {
            throw new ToolFailure("The public status {$group->code} is deleted. orders_public_statuses_restore brings it back.");
        }

        $ids = $this->ids($arguments['ids'] ?? null, 'ids');
        $groups = $this->groups();
        $live = array_merge(...array_values($groups ?: [[]]));
        $unknown = array_diff($ids, $live);

        if ($unknown !== []) {
            throw new ToolFailure('No live internal status ['.implode('], [', $unknown).']: orders_statuses_list has the ids, and a deleted one keeps its place.');
        }

        foreach ($groups as $key => $members) {
            $groups[$key] = array_values(array_diff($members, $ids));
        }

        $groups[$group->id] = [...$ids, ...$groups[$group->id]];

        return $this->arranged(array_keys($groups), $groups, $this->dryRun($arguments));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function savePublic(array $arguments): array
    {
        $code = strtolower(trim((string) ($arguments['code'] ?? '')));
        $status = PublicStatus::withTrashed()->where('code', $code)->first();

        if ($status?->trashed()) {
            throw new ToolFailure("The public status {$code} is deleted. orders_public_statuses_restore brings it back first.");
        }

        $input = [
            'code' => $code,
            'name' => [...($status?->getTranslations('name') ?? []), ...$this->words($arguments['name'] ?? [], 'name')],
            'description' => [...($status?->getTranslations('description') ?? []), ...$this->words($arguments['description'] ?? [], 'description')],
        ];

        $dryRun = $this->dryRun($arguments);
        $work = fn (): array => $this->publicRow($this->statuses->writePublic($input, $status));

        return [
            'created' => $status === null,
            'public_status' => $this->attempt(fn (): array => $dryRun ? Rehearsal::run($work, (new PublicStatus)->getConnectionName()) : $work()),
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function removePublic(array $arguments): array
    {
        $group = $this->group($arguments['code'] ?? null);
        $moveTo = isset($arguments['move_to']) ? $this->group($arguments['move_to']) : null;

        if ($group->trashed()) {
            throw new ToolFailure("The public status {$group->code} is deleted already.");
        }

        return $this->planned('delete-public', $this->dryRun($arguments), fn (bool $dryRun): StatusPlan => $this->statuses->removePublic($group, $moveTo, $dryRun))['said'];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function restorePublic(array $arguments): array
    {
        $group = $this->group($arguments['code'] ?? null);
        $dryRun = $this->dryRun($arguments);

        if (! $dryRun) {
            $this->statuses->restorePublic($group);
        }

        return ['public_status' => [...$this->publicRow($group), 'deleted' => false], 'dry_run' => $dryRun];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorderPublic(array $arguments): array
    {
        $codes = $arguments['codes'] ?? null;

        if (! is_array($codes) || $codes === []) {
            throw new ToolFailure('`codes` is the list of public status codes, first to last.');
        }

        $groups = $this->groups();
        $named = [];

        foreach ($codes as $code) {
            $group = $this->group($code);

            if (! array_key_exists($group->id, $groups)) {
                throw new ToolFailure("The public status {$group->code} is deleted: it keeps its place.");
            }

            $named[] = $group->id;
        }

        $publics = array_values(array_unique([...$named, ...array_keys($groups)]));

        return $this->arranged($publics, $groups, $this->dryRun($arguments));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function settingsSet(array $arguments, ?Authenticatable $caller): array
    {
        $given = $arguments['values'] ?? null;

        if (! is_array($given) || $given === []) {
            throw new ToolFailure('`values` is an object of setting → value; settings_get lists the keys.');
        }

        Arguments::refuseUnknown($given, array_keys(OrderSettings::KEYS), 'orders_settings_set `values`');

        if ($this->settings->stored() === null) {
            throw new ToolFailure('This site keeps no settings (module-settings is not installed): the config webx-orders decides, and a person changes it in the code.');
        }

        $can = $caller instanceof HasPermissions ? static fn (string $permission): bool => $caller->hasPermission($permission) : null;

        return $this->attempt(function () use ($given, $can, $arguments): array {
            $valid = $this->values->validate(SettingsScreen::NAME, [...$this->settings->all(), ...$given], $can);

            if ($this->dryRun($arguments)) {
                $this->settings->check($valid);

                return [...$this->settings->describe(), 'sample' => $this->settings->sample($valid), 'dry_run' => true];
            }

            $this->settings->store($valid);

            return [...$this->settings->describe(), 'dry_run' => false];
        });
    }

    /**
     * The whole arrangement with the change in it — `arrange()` names every live status once.
     *
     * @param  list<int>  $publics
     * @param  array<int, list<int>>  $groups
     * @return array<string, mixed>
     */
    private function arranged(array $publics, array $groups, bool $dryRun): array
    {
        $said = $this->planned('arrange', $dryRun, fn (bool $dry): StatusPlan => $this->statuses->arrange($publics, $groups, $dry))['said'];

        return [...$said, 'groups' => $dryRun ? null : $this->board->describe(app()->getLocale())['groups'], 'dry_run' => $dryRun];
    }

    /**
     * The live public groups in their order, each with its live statuses in theirs.
     *
     * @return array<int, list<int>>
     */
    private function groups(): array
    {
        $groups = [];

        foreach (PublicStatus::query()->orderBy('position')->orderBy('id')->pluck('id') as $id) {
            $groups[(int) $id] = [];
        }

        foreach (OrderStatus::query()->orderBy('position')->orderBy('id')->get(['id', 'public_status_id']) as $status) {
            if (array_key_exists($status->public_status_id, $groups)) {
                $groups[$status->public_status_id][] = $status->id;
            }
        }

        return $groups;
    }

    /**
     * A dry run answers the consequences; a real change answers them too, with the status it wrote.
     * A refusal is a `ToolFailure` in the panel's words.
     *
     * @param  'delete'|'delete-public'|'save'|'arrange'|'restore'  $change
     * @param  Closure(bool): StatusPlan  $work
     * @return array{said: array<string, mixed>, id: int|null}
     */
    private function planned(string $change, bool $dryRun, Closure $work): array
    {
        $locale = app()->getLocale();

        try {
            $plan = $this->attempt(static fn (): StatusPlan => $work($dryRun));
        } catch (StatusRefused $refused) {
            $said = $this->board->consequences($refused->plan, $change, $locale);
            $alive = array_column($said['active'], 'name');

            throw new ToolFailure((string) $said['message'].($alive !== [] ? ' Its statuses: '.implode(', ', $alive).'.' : ''));
        }

        return ['said' => [...$this->board->consequences($plan, $change, $locale), 'dry_run' => $dryRun], 'id' => $plan->id];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicRow(PublicStatus $group): array
    {
        return [
            'id' => $group->id,
            'code' => $group->code,
            'name' => $group->getTranslations('name'),
            'description' => $group->getTranslations('description'),
            'deleted' => $group->trashed(),
        ];
    }

    private function internal(mixed $id): OrderStatus
    {
        if (! is_int($id)) {
            throw new ToolFailure('`id` is the internal status\'s id (orders_statuses_list).');
        }

        return OrderStatus::withTrashed()->find($id) ?? throw new ToolFailure("There is no status {$id}. orders_statuses_list lists them.");
    }

    private function group(mixed $code): PublicStatus
    {
        $code = strtolower(trim(is_scalar($code) ? (string) $code : ''));

        return PublicStatus::withTrashed()->where('code', $code)->first()
            ?? throw new ToolFailure("There is no public status [{$code}]. orders_statuses_list lists them by code.");
    }

    /**
     * @return array<string, string>
     */
    private function words(mixed $words, string $name): array
    {
        if (! is_array($words)) {
            throw new ToolFailure("`{$name}` is an object of language code → text.");
        }

        // An empty one is kept empty, so the service leaves that language out.
        return array_map(static fn (mixed $text): string => is_scalar($text) ? (string) $text : '', $words);
    }

    /**
     * @return list<int>
     */
    private function ids(mixed $ids, string $name): array
    {
        if (! is_array($ids) || $ids === [] || array_filter($ids, is_int(...)) !== $ids) {
            throw new ToolFailure("`{$name}` is a list of ids.");
        }

        return array_values(array_unique($ids));
    }
}
