<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Cart\Money;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrderDetails;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Panel\Consequences;
use WebxUi\Orders\Panel\OrderCard;
use WebxUi\Orders\Panel\OrderList;
use WebxUi\Orders\Panel\OrdersSummary;
use WebxUi\Orders\Panel\StatusChoices;
use WebxUi\Orders\RefundRefused;
use WebxUi\Orders\RefundRequest;
use WebxUi\Orders\RefundResult;
use WebxUi\Orders\Refusal;
use WebxUi\Orders\StatusMoveRefused;

/**
 * `/api/cms/orders` (spec §11.2, §11.3): the list with its views and filters, the card, and what
 * the card does — the status with its consequences, the marks of payment and receipt, the
 * cancellation, the contacts and the note, a refund.
 *
 * Every change is a call of `Orders` (decision 18): this class reads, checks the request's shape
 * and turns the service's answer — or its refusal — into the panel's words. It never writes an
 * order itself, which `BoundariesTest` holds it to. A dry run is the service's own, so the dialog
 * shows exactly what the move will do.
 */
final readonly class OrderController
{
    public function __construct(
        private Orders $orders,
        private OrderList $list,
        private OrderCard $card,
        private Consequences $consequences,
        private Money $money,
    ) {}

    /**
     * A page of the list with how many each tab holds. `view=auto` is the list opened without a
     * tab (decision 92): «New» while it holds any order, otherwise «All» — told by the same counts,
     * so it is still one answer, and `view` says which tab it is.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([...OrderList::rules(), 'view' => ['nullable', Rule::in([...OrderList::VIEWS, 'auto'])]]);

        $locale = app()->getLocale();
        $statuses = new StatusChoices;
        $counts = $this->list->counts($validated);
        $view = (string) ($validated['view'] ?? 'all');

        if ($view === 'auto') {
            $view = $counts['new'] > 0 ? 'new' : 'all';
        }

        $page = $this->list->page([...$validated, 'view' => $view], (int) ($validated['per_page'] ?? 25), (int) ($validated['page'] ?? 1));

        return new JsonResponse([
            ...$page->toArray(),
            'data' => array_map(fn (Order $order): array => $this->list->row($order, $statuses, $locale), $page->items()),
            'counts' => $counts,
            'view' => $view,
        ]);
    }

    /**
     * The figures over the list (§11.2): the same count as the agent's `orders_summary`, asked
     * apart so that the rows never wait for it.
     */
    public function summary(OrdersSummary $summary): JsonResponse
    {
        return ApiResponse::data($summary->describe(app()->getLocale()));
    }

    /** An opened row of the list: what the order holds and who it is for, without the card. */
    public function glance(Request $request, int $order): JsonResponse
    {
        return ApiResponse::data($this->card->glance(self::find($order), app()->getLocale(), $request->user()));
    }

    /**
     * What the filters choose from: both sets of statuses, the methods of the blocks, and
     * whether «guest or visitor» means anything here — only with the visitors installed.
     */
    public function meta(ModuleRegistry $modules): JsonResponse
    {
        return ApiResponse::data([
            'statuses' => (new StatusChoices)->grouped(app()->getLocale()),
            'methods' => $this->list->methods(),
            'initial_status' => $this->list->initialStatus(),
            'visitors' => $modules->has('users'),
        ]);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        return $this->describe($request, self::find($order));
    }

    /**
     * Another internal status (§11.3): `dry_run` answers the line of consequences, a real move
     * answers the card.
     */
    public function status(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'status_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $found = self::find($order);
        $statuses = new StatusChoices;
        $target = $statuses->model((int) $validated['status_id']) ?? throw ValidationException::withMessages([
            'status_id' => (string) __('webx-orders::panel.errors.no-status'),
        ]);

        $dryRun = (bool) ($validated['dry_run'] ?? false);

        try {
            $move = $this->orders->moveTo($found, $target, 'panel', self::previewNote($validated['note'] ?? null, $dryRun), $dryRun);
        } catch (StatusMoveRefused $refused) {
            throw ValidationException::withMessages(['status_id' => (string) __('webx-orders::panel.refusals.'.$refused->reason)]);
        }

        if ($move->dryRun) {
            return ApiResponse::data($this->consequences->move($move, $statuses, app()->getLocale()));
        }

        return $this->describe($request, $found);
    }

    /** «Mark as paid», «Mark as received» (§5.1): a reference and a note, the source `panel`. */
    public function mark(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'milestone' => ['required', Rule::in([Milestone::Paid->value, Milestone::Received->value])],
            'reference' => ['nullable', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $found = self::find($order);

        $this->refusing(fn () => $this->orders->mark(
            $found,
            Milestone::from((string) $validated['milestone']),
            'panel',
            reference: $validated['reference'] ?? null,
            note: $validated['note'] ?? null,
        ), 'milestone');

        return $this->describe($request, $found);
    }

    /**
     * A note for whoever picks the order up next (decision 90): a line of its history of the kind
     * `note`, written as `Orders::note()` writes a module's — the author the administrator. It never
     * changes afterwards; the answer is the card, with the note in its feed and its history.
     */
    public function note(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $found = self::find($order);
        $this->orders->note($found, (string) $validated['body'], 'panel');

        return $this->describe($request, $found);
    }

    /**
     * The cancellation with its reason (§9.1). `dry_run` answers what it does — whether the
     * order was paid, how much money is left to give back, the tenders that go back.
     */
    public function cancel(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $found = self::find($order);
        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $reason = $validated['reason'] ?? null;
        $reason = self::previewNote($reason, $dryRun);
        $mark = $this->refusing(fn () => $this->orders->cancel($found, 'panel', $reason, $dryRun), 'reason');

        if ($mark->dryRun) {
            return ApiResponse::data($this->consequences->cancel($mark, $found->currency, $this->money));
        }

        return $this->describe($request, $found);
    }

    /**
     * The contacts, the recipient, the comment and the manager note — the part of the card the
     * screen edits; the errors come under the screen's own names (`customer.phone`).
     */
    public function details(Request $request, int $order): JsonResponse
    {
        $values = $request->input('values');
        $values = is_array($values) ? $values : [];
        $text = static fn (string $key): ?string => isset($values[$key]) && is_scalar($values[$key]) ? (string) $values[$key] : null;

        $found = self::find($order);
        $patch = new OrderDetails;

        if (array_intersect(array_keys($values), ['customer.first_name', 'customer.last_name', 'customer.phone', 'customer.email']) !== []) {
            $current = OrderCard::values($found);
            $pick = static fn (string $key): ?string => array_key_exists($key, $values) ? $text($key) : $current[$key];
            $patch = $patch->customer(new Contact((string) $pick('customer.first_name'), $pick('customer.last_name'), $pick('customer.phone'), $pick('customer.email')));
        }

        if (array_intersect(array_keys($values), ['recipient.first_name', 'recipient.last_name', 'recipient.phone']) !== []) {
            $first = trim((string) $text('recipient.first_name'));
            $last = $text('recipient.last_name');
            $phone = $text('recipient.phone');
            // A recipient emptied whole is «the buyer takes it», not a recipient without a name.
            $patch = $patch->recipient($first === '' && trim((string) $last) === '' && trim((string) $phone) === ''
                ? null
                : new Contact($first, $last, $phone));
        }

        foreach (['comment' => 'comment', 'manager_note' => 'managerNote'] as $key => $method) {
            if (array_key_exists($key, $values)) {
                $patch = $patch->{$method}($text($key));
            }
        }

        $this->orders->updateDetails($found, $patch, 'panel');

        return $this->describe($request, $found);
    }

    /**
     * Money given back outside the site (§9.3, §9.4): the amounts typed in the currency's own
     * units, each part checked against what is left. `dry_run` answers what is left and whether
     * it would be full; a refusal comes back under the field it is about.
     */
    public function refund(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'tenders' => ['nullable', 'array'],
            'tenders.*' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:190'],
            'reason' => ['nullable', 'string', 'max:500'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $found = self::find($order);
        $currency = $found->currency;
        $minor = fn (mixed $amount): int => $amount === null || $amount === '' ? 0 : $this->money->toMinor((string) $amount, $currency);
        $refund = new RefundRequest(
            $minor($validated['amount'] ?? null),
            array_map($minor, array_filter((array) ($validated['tenders'] ?? []), static fn (mixed $one): bool => $one !== null && $one !== '')),
            array_map(intval(...), array_filter((array) ($validated['items'] ?? []), static fn (mixed $one): bool => $one !== null && $one !== '')),
            $validated['reference'] ?? null,
            $validated['reason'] ?? null,
            'panel',
        );

        if ((bool) ($validated['dry_run'] ?? false)) {
            return ApiResponse::data($this->refundAnswer($this->orders->refund($found, $refund, true), $currency));
        }

        try {
            $this->orders->refund($found, $refund);
        } catch (RefundRefused $refused) {
            throw ValidationException::withMessages([
                (string) ($refused->result->field ?? 'amount') => $this->refundRefusal($refused->result, $currency),
            ]);
        }

        return $this->describe($request, $found);
    }

    private function describe(Request $request, Order $order): JsonResponse
    {
        $reader = $request->user();

        return ApiResponse::data($this->card->describe(
            $order->refresh(),
            new StatusChoices,
            app()->getLocale(),
            static fn (string $permission): bool => $reader instanceof HasPermissions && $reader->hasPermission($permission),
            $reader,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function refundAnswer(RefundResult $result, string $currency): array
    {
        return [
            'money_left' => $result->moneyLeft,
            'money_left_text' => $this->money->format($result->moneyLeft, $currency),
            'tenders_left' => $result->tendersLeft,
            'items_left' => $result->itemsLeft,
            'full' => $result->full,
            'milestone' => $result->milestone !== null ? Consequences::milestone($result->milestone) : null,
            'refusal' => $result->refusal,
            'field' => $result->field,
            'message' => $result->refusal !== null ? $this->refundRefusal($result, $currency) : null,
        ];
    }

    private function refundRefusal(RefundResult $result, string $currency): string
    {
        return (string) __('webx-orders::panel.refund-refusals.'.$result->refusal, [
            'left' => $this->money->format($result->moneyLeft, $currency),
        ]);
    }

    /**
     * The service's refusal as a form's error: nothing was written, and the panel says why.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function refusing(callable $work, string $field): mixed
    {
        try {
            return $work();
        } catch (Refusal $refused) {
            throw ValidationException::withMessages([$field => (string) __('webx-orders::panel.refusals.'.$refused->reason)]);
        }
    }

    /**
     * The dialog asks what a cancellation — or a status that cancels — does before the reason is
     * typed; a dry run writes nothing, so the reason it is asked with is never kept anywhere. The
     * real call gets the reason as typed, and the service refuses an empty one.
     */
    public static function previewNote(?string $note, bool $dryRun): ?string
    {
        return $dryRun && ($note === null || trim($note) === '') ? '…' : $note;
    }

    private static function find(int $id): Order
    {
        $order = Order::query()->find($id);

        if (! $order instanceof Order) {
            abort(404, (string) __('webx-orders::panel.errors.not-found', ['id' => $id]));
        }

        return $order;
    }
}
