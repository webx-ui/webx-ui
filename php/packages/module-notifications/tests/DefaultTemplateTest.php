<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Notifications\Channels\ChannelForm;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Events\Variable;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\NotificationEvents;

/**
 * The default template an event declares for each provider (§5, decision 1): one text for every
 * language, or a function of the language — what a new channel starts with, what the panel's
 * "Insert the default template" and `default_template: true` put back, and what an agent reads.
 */
final class DefaultTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Words in English and Russian only; the site also has German, which it has none in.
        config()->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'ru'], ['code' => 'de']]);

        $this->app->make(NotificationEvents::class)->register(new EventDefinition(
            key: 'shop.review',
            label: 'Review left',
            module: 'shop',
            variables: [new Variable('stars', 'Stars', example: '5')],
            sample: [
                'email' => static fn (string $locale): ?array => match ($locale) {
                    'en' => ['subject' => 'Review: {{stars}}', 'template' => '<p>{{stars}} stars</p>'],
                    'ru' => ['subject' => 'Отзыв: {{stars}}', 'template' => '<p>Звёзд: {{stars}}</p>'],
                    default => null,
                },
            ],
        ));

        // A letter to the shop and one to the buyer, as `orders.placed` declares them (§5.1).
        $this->app->make(NotificationEvents::class)->register(new EventDefinition(
            key: 'shop.sold',
            label: 'Sold',
            module: 'shop',
            variables: [
                new Variable('number', 'Number', example: '7'),
                new Variable('buyer_email', 'Buyer', example: 'buyer@example.com', address: 'email'),
                new Variable('other_email', 'Someone else', example: 'other@example.com', address: 'email'),
            ],
            sample: [
                'email' => ['subject' => 'Sold: {{number}}', 'template' => 'New order {{number}}'],
                '*' => ['template' => 'Sold {{number}}'],
            ],
            addressed: [
                'buyer_email' => [
                    'email' => static fn (string $locale): ?array => match ($locale) {
                        'en' => ['subject' => 'Thank you: {{number}}', 'template' => 'We got your order {{number}}'],
                        'ru' => ['subject' => 'Спасибо: {{number}}', 'template' => 'Мы получили заказ {{number}}'],
                        default => null,
                    },
                ],
            ],
        ));
    }

    #[Test]
    public function a_sample_by_language_fills_the_languages_it_has_words_in_and_the_main_one_always(): void
    {
        $event = $this->review();

        $this->assertSame(['subject' => 'Отзыв: {{stars}}', 'template' => '<p>Звёзд: {{stars}}</p>'], $event->sampleFor('email', 'ru'));
        $this->assertNull($event->sampleFor('email', 'de'));
        $this->assertNull($event->sampleFor('telegram', 'en'));

        $this->assertSame([
            'subject' => ['en' => 'Review: {{stars}}', 'ru' => 'Отзыв: {{stars}}'],
            'template' => ['en' => '<p>{{stars}} stars</p>', 'ru' => '<p>Звёзд: {{stars}}</p>'],
        ], $event->samplesFor('email', ['en', 'ru', 'de'], 'en'));

        // A main language without words gets the fallback language's.
        $this->assertSame(['de' => '<p>{{stars}} stars</p>', 'ru' => '<p>Звёзд: {{stars}}</p>'], $event->samplesFor('email', ['de', 'ru'], 'de')['template'] ?? null);

        // One text for every language is the main language's, as it always was.
        $this->assertSame(['en' => '<b>Order {{number}}</b> — {{total}}'], $this->order()->samplesFor('telegram', ['en', 'ru'], 'en')['template'] ?? null);
    }

    #[Test]
    public function the_panel_reads_the_samples_in_the_languages_of_the_site(): void
    {
        $response = $this->actingAs($this->admin(), 'cms')->getJson('/api/cms/notifications/meta')->assertOk();
        $at = array_search('shop.review', (array) $response->json('data.events.*.key'), true);
        $this->assertIsInt($at);

        $response
            ->assertJsonPath("data.events.{$at}.sample.email.template", ['en' => '<p>{{stars}} stars</p>', 'ru' => '<p>Звёзд: {{stars}}</p>'])
            ->assertJsonPath("data.events.{$at}.sample.email.subject", ['en' => 'Review: {{stars}}', 'ru' => 'Отзыв: {{stars}}'])
            ->assertJsonMissingPath("data.events.{$at}.sample.telegram");
    }

    #[Test]
    public function a_new_channel_starts_with_it_and_default_template_puts_it_back(): void
    {
        $form = $this->app->make(ChannelForm::class);
        $channel = $form->save(new Channel, ['name' => 'Reviews', 'provider' => 'email', 'event' => 'shop.review'])['channel'];

        $this->assertSame(['en' => '<p>{{stars}} stars</p>', 'ru' => '<p>Звёзд: {{stars}}</p>'], $channel->getTranslations('template'));

        $channel = $form->save($channel, ['template' => ['en' => 'Mine', 'ru' => 'Моё'], 'subject' => ['en' => 'Mine']])['channel'];
        $this->assertSame('Mine', $channel->getTranslation('template', 'en'));

        $channel = $form->save($channel, ['default_template' => true])['channel'];
        $this->assertSame(['en' => '<p>{{stars}} stars</p>', 'ru' => '<p>Звёзд: {{stars}}</p>'], $channel->getTranslations('template'));
        $this->assertSame(['en' => 'Review: {{stars}}', 'ru' => 'Отзыв: {{stars}}'], $channel->getTranslations('subject'));

        // An event without one for the provider says so.
        $telegram = $this->telegram(['event' => 'shop.review', 'template' => ['en' => '{{stars}}']]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The event "shop.review" has no default template for this kind of channel.');
        $form->save($telegram, ['default_template' => true]);
    }

    #[Test]
    public function an_agent_reads_it_previews_it_and_puts_it_on_a_channel_with_a_dry_run_first(): void
    {
        $read = $this->content($this->agent('notifications_default_template', ['event' => 'shop.review', 'provider' => 'email']));

        $this->assertSame(['en' => '<p>{{stars}} stars</p>', 'ru' => '<p>Звёзд: {{stars}}</p>'], $read['template']);
        $this->assertSame(['en' => 'Review: {{stars}}', 'ru' => 'Отзыв: {{stars}}'], $read['subject']);
        $this->assertTrue($read['html']);
        $this->assertSame('<p>5 stars</p>', $read['preview']['body']);
        $this->assertSame('Review: 5', $read['preview']['subject']);
        $this->assertSame('Отзыв: 5', $this->content($this->agent('notifications_default_template', ['event' => 'shop.review', 'provider' => 'email', 'locale' => 'ru']))['preview']['subject']);

        $this->agent('notifications_default_template', ['event' => 'shop.review', 'provider' => 'telegram'])
            ->assertHasErrors(['Event [shop.review] has no default template for [telegram]']);
        $this->agent('notifications_default_template', ['event' => 'shop.nothing', 'provider' => 'email'])
            ->assertHasErrors(['Event [shop.nothing] is not declared.']);

        // Created without a template: the default one, in every language it has.
        $created = $this->content($this->agent('notifications_channel_create', ['name' => 'Reviews', 'provider' => 'email', 'event' => 'shop.review']));
        $this->assertSame(['en' => '<p>{{stars}} stars</p>', 'ru' => '<p>Звёзд: {{stars}}</p>'], $created['values']['template']);

        $id = $created['channel']['id'];
        $this->agent('notifications_channel_update', ['channel' => $id, 'template' => 'Mine'])->assertOk();

        $rehearsed = $this->content($this->agent('notifications_channel_update', ['channel' => $id, 'default_template' => true, 'dry_run' => true]));
        $this->assertTrue($rehearsed['dry_run']);
        $this->assertSame('<p>{{stars}} stars</p>', $rehearsed['values']['template']['en']);
        $this->assertSame('Mine', Channel::query()->findOrFail($id)->getTranslation('template', 'en'));

        $this->agent('notifications_channel_update', ['channel' => $id, 'default_template' => true])->assertOk();
        $this->assertSame('<p>{{stars}} stars</p>', Channel::query()->findOrFail($id)->getTranslation('template', 'en'));
    }

    #[Test]
    public function a_channel_to_an_addressee_gets_the_addressee_s_template_and_any_other_the_event_s(): void
    {
        $event = $this->sold();

        $this->assertSame('buyer_email', $event->addresseeOf([['email' => 'shop@example.com'], ['variable' => 'buyer_email']], 'email'));
        $this->assertNull($event->addresseeOf([['variable' => 'other_email']], 'email'), 'an addressee without a template of its own');
        $this->assertNull($event->addresseeOf([['variable' => 'buyer_email']], 'telegram'), 'nothing of its own for the provider');
        $this->assertNull($event->addresseeOf(null, 'email'));

        $form = $this->app->make(ChannelForm::class);
        $buyer = $form->save(new Channel, ['name' => 'To the buyer', 'provider' => 'email', 'event' => 'shop.sold', 'recipients' => [['variable' => 'buyer_email']]])['channel'];
        $shop = $form->save(new Channel, ['name' => 'To the shop', 'provider' => 'email', 'event' => 'shop.sold', 'recipients' => [['email' => 'shop@example.com']]])['channel'];
        $other = $form->save(new Channel, ['name' => 'To someone', 'provider' => 'email', 'event' => 'shop.sold', 'recipients' => [['variable' => 'other_email']]])['channel'];

        $this->assertSame(['en' => 'We got your order {{number}}', 'ru' => 'Мы получили заказ {{number}}'], $buyer->getTranslations('template'));
        $this->assertSame(['en' => 'Thank you: {{number}}', 'ru' => 'Спасибо: {{number}}'], $buyer->getTranslations('subject'));
        $this->assertSame(['en' => 'New order {{number}}'], $shop->getTranslations('template'));
        $this->assertSame(['en' => 'New order {{number}}'], $other->getTranslations('template'));

        // The recipients changed to the buyer, then the default put back: the buyer's.
        $shop = $form->save($shop, ['recipients' => [['variable' => 'buyer_email']], 'default_template' => true])['channel'];
        $this->assertSame('We got your order {{number}}', $shop->getTranslation('template', 'en'));

        // A saved channel is not touched by anything but asking (decision 6 of the orders).
        $buyer = $form->save($buyer, ['recipients' => [['email' => 'shop@example.com']]])['channel'];
        $this->assertSame('We got your order {{number}}', $buyer->getTranslation('template', 'en'));
        $buyer = $form->save($buyer, ['default_template' => true])['channel'];
        $this->assertSame(['en' => 'New order {{number}}'], $buyer->getTranslations('template'));

        // An event with one template for everybody: as before, whoever the channel writes to.
        $reviews = $form->save(new Channel, ['name' => 'Reviews', 'provider' => 'email', 'event' => 'shop.order', 'recipients' => [['variable' => 'customer_email']]])['channel'];
        $this->assertSame(['en' => "Order {{number}}\nTotal: {{total}}"], $reviews->getTranslations('template'));
    }

    #[Test]
    public function the_panel_reads_the_addressee_s_template_beside_the_event_s(): void
    {
        $response = $this->actingAs($this->admin(), 'cms')->getJson('/api/cms/notifications/meta')->assertOk();
        $at = array_search('shop.sold', (array) $response->json('data.events.*.key'), true);
        $review = array_search('shop.review', (array) $response->json('data.events.*.key'), true);
        $this->assertIsInt($at);
        $this->assertIsInt($review);

        $response
            ->assertJsonPath("data.events.{$at}.sample.email.template", ['en' => 'New order {{number}}'])
            ->assertJsonPath("data.events.{$at}.addressed.buyer_email.email.template", ['en' => 'We got your order {{number}}', 'ru' => 'Мы получили заказ {{number}}'])
            ->assertJsonMissingPath("data.events.{$at}.addressed.buyer_email.telegram")
            ->assertJsonPath("data.events.{$review}.addressed", []);
    }

    #[Test]
    public function an_agent_reads_the_template_of_an_addressee_and_a_channel_to_it_starts_with_it(): void
    {
        $buyer = $this->content($this->agent('notifications_default_template', ['event' => 'shop.sold', 'provider' => 'email', 'addressee' => 'buyer_email']));
        $this->assertSame(['en' => 'We got your order {{number}}', 'ru' => 'Мы получили заказ {{number}}'], $buyer['template']);
        $this->assertSame('buyer_email', $buyer['addressee']);
        $this->assertTrue($buyer['own']);
        $this->assertSame('We got your order 7', $buyer['preview']['body']);
        $this->assertSame('Thank you: 7', $buyer['preview']['subject']);

        $shop = $this->content($this->agent('notifications_default_template', ['event' => 'shop.sold', 'provider' => 'email']));
        $this->assertSame(['en' => 'New order {{number}}'], $shop['template']);
        $this->assertSame('New order 7', $shop['preview']['body']);

        // An addressee without a template of its own: the event's, said so.
        $other = $this->content($this->agent('notifications_default_template', ['event' => 'shop.sold', 'provider' => 'email', 'addressee' => 'other_email']));
        $this->assertSame(['en' => 'New order {{number}}'], $other['template']);
        $this->assertFalse($other['own']);

        $this->agent('notifications_default_template', ['event' => 'shop.sold', 'provider' => 'email', 'addressee' => 'number'])
            ->assertHasErrors(['[number] is not an addressee of [shop.sold] for [email]: buyer_email, other_email.']);

        $created = $this->content($this->agent('notifications_channel_create', [
            'name' => 'To the buyer', 'provider' => 'email', 'event' => 'shop.sold', 'recipients' => [['variable' => 'buyer_email']],
        ]));
        $this->assertSame('We got your order {{number}}', $created['values']['template']['en']);

        $id = $created['channel']['id'];
        $this->agent('notifications_channel_update', ['channel' => $id, 'recipients' => [['email' => 'shop@example.com']]])->assertOk();
        $this->assertSame('We got your order {{number}}', Channel::query()->findOrFail($id)->getTranslation('template', 'en'));

        $rehearsed = $this->content($this->agent('notifications_channel_update', ['channel' => $id, 'default_template' => true, 'dry_run' => true]));
        $this->assertSame('New order {{number}}', $rehearsed['values']['template']['en']);

        $preview = $this->content($this->agent('notifications_preview', ['channel' => $id]));
        $this->assertSame('We got your order 7', $preview['body']);
    }

    private function sold(): EventDefinition
    {
        $event = $this->app->make(NotificationEvents::class)->find('shop.sold');
        $this->assertNotNull($event);

        return $event;
    }

    private function review(): EventDefinition
    {
        $event = $this->app->make(NotificationEvents::class)->find('shop.review');
        $this->assertNotNull($event);

        return $event;
    }

    private function order(): EventDefinition
    {
        $event = $this->app->make(NotificationEvents::class)->find('shop.order');
        $this->assertNotNull($event);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->admin(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $response->assertOk();
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
