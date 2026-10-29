<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http\Controllers\Site;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Orders\Site\OrderPage;
use WebxUi\Users\Urls;

/**
 * «My orders» (spec §13.2) — a section of the account of `webx-ui/module-users`, behind
 * `webx.visitor`: the visitor's own orders, newest first, and one of them as the signed page
 * shows it, without the signature — the owner is checked instead. Another visitor's order, or a
 * guest's with the same number, is a 404, as a number that does not exist.
 */
final readonly class AccountOrdersController
{
    public function __construct(
        private Visitors $visitors,
        private OrderPage $page,
        private Urls $urls,
    ) {}

    public function index(Request $request, Views $views, Config $config): View
    {
        $perPage = max(1, (int) $config->get('webx-orders.account_per_page', 20));
        $orders = $this->mine()
            ->select(['id', 'number', 'currency', 'status_id', 'payable', 'placed_at', 'cancelled_at'])
            ->withCount(['items as lines_count' => static fn (Builder $items) => $items->whereNull('parent_id')])
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $rows = array_map(fn (array $row): array => [
            ...$row,
            'url' => $this->urls->named(OrderLinks::ACCOUNT_ORDER, ['number' => $row['number']]),
        ], $this->page->rows($orders->items()));

        return $views->make('webx-orders::account.index', ['rows' => $rows, 'pages' => $orders]);
    }

    public function show(Request $request, Views $views): View
    {
        // By name: behind the language prefix the first parameter of the route is the language.
        $order = $this->mine()->where('number', (string) $request->route('number'))->first() ?? throw new NotFoundHttpException;

        return $views->make('webx-orders::account.show', [
            'order' => $this->page->of($order, app()->getLocale()),
            'back' => $this->urls->named(OrderLinks::ACCOUNT),
        ]);
    }

    /** @return Builder<Order> */
    private function mine(): Builder
    {
        // `webx.visitor` stands in front: there is somebody.
        $visitor = $this->visitors->current() ?? throw new NotFoundHttpException;

        return Order::query()->where('user_id', $visitor->id);
    }
}
