<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Panel\EventNames;
use WebxUi\Payments\Panel\EventRows;
use WebxUi\Payments\Panel\ExchangeQuery;
use WebxUi\Payments\Panel\PaymentsSection;

/**
 * The exchange journal (spec §13.2, §20.7): every event newest first, a keyset page at a time,
 * filtered by gateway, method, attempt or order, kind, direction, bad signatures, errors and
 * period; one event with its trace; a body to download.
 *
 * Read only (decision 31): there is no route that changes or removes an event, for any right.
 * Headers and bodies are behind `payments.exchange` (decision 47); without it an event is the line.
 */
final class ExchangeController
{
    public function __construct(private readonly EventNames $names) {}

    public function index(Request $request, Gateways $gateways): JsonResponse
    {
        $page = ExchangeQuery::page($request->query(), (int) $request->query('per_page', (string) ExchangeQuery::PER_PAGE));

        return ApiResponse::data([
            'data' => $this->names->apply($page['rows']->map(static fn (PaymentGatewayEvent $event): array => EventRows::line($event))->values()->all(), $request->user()),
            'next' => $page['next'],
            'filters' => ExchangeQuery::filters($gateways, app()->getLocale()),
        ]);
    }

    /** One event and its trace — the neighbours of the same action (§13.2). */
    public function show(Request $request, int $event): JsonResponse
    {
        /** @var PaymentGatewayEvent $row */
        $row = PaymentGatewayEvent::query()->findOrFail($event);
        $trace = EventRows::query()->where('trace', $row->trace)->orderBy('id')->limit(100)->get();

        return ApiResponse::data([
            ...$this->names->apply([EventRows::detail($row, $this->bodies($request))], $request->user())[0],
            'trace_events' => $this->names->apply($trace->map(static fn (PaymentGatewayEvent $one): array => EventRows::line($one))->values()->all(), $request->user()),
        ]);
    }

    /**
     * «Download» a body (§20.7): as stored, an attachment that never runs in the panel's page.
     */
    public function body(int $event, string $part): Response
    {
        abort_unless(in_array($part, ['request', 'response'], true), 404);

        /** @var PaymentGatewayEvent $row */
        $row = PaymentGatewayEvent::query()->findOrFail($event);
        $body = EventRows::body($row, $part);

        abort_if($body === null, 404);

        return new Response($body, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="exchange-'.$row->id.'-'.$part.'.bin"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function bodies(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof HasPermissions && $user->hasPermission(PaymentsSection::EXCHANGE);
    }
}
