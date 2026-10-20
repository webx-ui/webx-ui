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
