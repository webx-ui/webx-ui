<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Orders;

/**
 * «Notes» of the card (decision 90): a manager's note is a line of the order's history of the kind
 * `note`, written through `Orders::note()`, and the card reads every such line into its feed — the
 * panel's, an agent's `orders_note`, a module's — with its author.
 */
final class NotesTest extends PanelTestCase
{
    #[Test]
    public function a_manager_s_note_is_a_line_of_the_history_and_comes_back_with_the_card(): void
    {
        $order = $this->order();
        $manager = $this->signIn();

        $answer = $this->postJson($this->api($order->id).'/notes', ['body' => "  Called the buyer.\nDeliver after six.  "])->assertOk();

        $this->assertSame([[
            'body' => "Called the buyer.\nDeliver after six.",
            'source' => 'panel',
            'author' => ['id' => $manager->id, 'name' => $manager->name, 'avatar' => null],
        ]], array_map(static fn (array $note): array => array_diff_key($note, ['id' => 1, 'created_at' => 1]), $answer->json('data.order.notes')));
        $this->assertTrue($answer->json('data.order.actions.note'));

        $line = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::NOTE)->sole();
        $this->assertSame($manager->id, $line->admin_id);
        $this->assertSame('panel', $line->source);

        // After a reload: the same note, and the history shows it too.
        $card = $this->getJson($this->api($order->id))->assertOk();
        $this->assertSame("Called the buyer.\nDeliver after six.", $card->json('data.order.notes.0.body'));
        $this->assertNotNull($card->json('data.order.notes.0.created_at'));
        $this->assertContains('note', array_column($card->json('data.order.timeline'), 'kind'));
    }

    #[Test]
    public function the_feed_holds_an_agent_s_and_a_module_s_notes_and_nothing_else_of_the_history(): void
    {
        $order = $this->order();
        $manager = $this->signIn();
        $orders = $this->app->make(Orders::class);
        $orders->note($order, 'Reserved until Friday', 'mcp');
        $orders->note($order, 'The parcel came back', 'delivery');

        $notes = $this->getJson($this->api($order->id))->assertOk()->json('data.order.notes');

        $this->assertSame(['Reserved until Friday', 'The parcel came back'], array_column($notes, 'body'));
        $this->assertSame(['mcp', 'delivery'], array_column($notes, 'source'));
        $this->assertSame($manager->name, $notes[0]['author']['name']);
        // A module writes as nobody: the panel names it by its source.
        $this->assertNull($notes[1]['author']);
    }

    #[Test]
    public function a_reader_sees_the_notes_and_may_not_write_one(): void
    {
        $order = $this->order();
        $this->app->make(Orders::class)->note($order, 'Called', 'delivery');

        $this->signIn(['orders.view']);

        $card = $this->getJson($this->api($order->id))->assertOk();
        $this->assertFalse($card->json('data.order.actions.note'));
        $this->assertSame(['Called'], array_column($card->json('data.order.notes'), 'body'));
        $this->postJson($this->api($order->id).'/notes', ['body' => 'Mine'])->assertForbidden();
    }

    #[Test]
    public function a_note_says_something_and_is_not_endless(): void
    {
        $order = $this->order();
        $this->signIn();

        $this->postJson($this->api($order->id).'/notes', ['body' => '   '])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson($this->api($order->id).'/notes', ['body' => str_repeat('a', 1001)])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson($this->api('999999').'/notes', ['body' => 'Called'])->assertNotFound();

        $this->assertSame(0, StatusChange::query()->where('kind', StatusChange::NOTE)->count());
    }
}
