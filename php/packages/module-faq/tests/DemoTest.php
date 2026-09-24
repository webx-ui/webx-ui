<?php

declare(strict_types=1);

namespace WebxUi\Faq\Tests;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blocks\Models\Block;
use WebxUi\Faq\Models\FaqCategory;
use WebxUi\Faq\Models\Question;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Pages\Models\Page;
use WebxUi\Services\Models\Service;
use WebxUi\Services\ServicesServiceProvider;

/**
 * The demo FAQ (§4.8): the rules it is there to show, and the block where a site would put it —
 * on `/faq` with the markup, in a service without it.
 */
final class DemoTest extends TestCase
{
    /**
     * With the services and the library they need: the payment questions go into a service.
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), MediaServiceProvider::class, ServicesServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_seeds_three_categories_ten_questions_and_the_block_type(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(3, FaqCategory::query()->count());
        $this->assertSame(10, Question::query()->count());
        $this->assertSame(1, Question::query()->where('published', false)->count());
        $this->assertTrue(Block::query()->where('slug', 'faq')->exists());

        $questions = Question::query()->get();
        $this->assertSame(9, $questions->filter(static fn (Question $question): bool => $question->visibleIn('en'))->count());
        // The unpublished one and the one without a translation.
        $this->assertSame(8, $questions->filter(static fn (Question $question): bool => $question->visibleIn('ru'))->count());
    }

    #[Test]
    public function one_question_stands_in_two_categories_in_a_different_place_in_each(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $prepayment = Question::query()->where('anchor', 'do-you-work-with-prepayment')->firstOrFail();
        [$payment, $process] = FaqCategory::query()->ordered()->get()->all();

        $inPayment = Question::query()->orderedIn((int) $payment->getKey())->pluck('id')->all();
        $inProcess = Question::query()->orderedIn((int) $process->getKey())->pluck('id')->all();

        $this->assertSame($prepayment->getKey(), $inPayment[1]);
        $this->assertSame($prepayment->getKey(), $inProcess[2]);
    }

    #[Test]
    public function the_faq_page_carries_the_markup_and_the_service_does_not(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $faq = $this->get('/faq')->assertOk();
        $faq->assertSee('How can I pay?');
        $faq->assertSee('How fast do you fix something urgent?');
        $faq->assertDontSee('Who writes the texts?');
        $faq->assertSee('b-faq__filter', false);
        $this->assertSame(1, substr_count((string) $faq->getContent(), '"FAQPage"'));

        $this->nextRequest();

        $service = $this->get('/services/company-website')->assertOk();
        $service->assertSee('Questions about payment');
        $service->assertSee('How can I pay?');
        $service->assertDontSee('How long does a site take?');
        $service->assertDontSee('"FAQPage"', false);
    }

    #[Test]
    public function the_second_language_does_not_see_the_question_it_has_no_translation_for(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $page = Page::query()->where('slug->en', 'faq')->firstOrFail();
        $this->assertSame('faq', $page->getTranslation('slug', 'ru', false));

        $this->nextRequest('ru');

        $this->get('/ru/faq')->assertOk()
            ->assertSee('Как можно оплатить?')
            ->assertDontSee('How fast do you fix something urgent?');
    }

    #[Test]
    public function removing_takes_everything_back_out(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Question::withTrashed()->count());
        $this->assertSame(0, FaqCategory::withTrashed()->count());
        $this->assertSame(0, Service::withTrashed()->count());
        $this->assertFalse(Page::withTrashed()->where('slug->en', 'faq')->exists());
        $this->assertFalse(Block::query()->where('slug', 'faq')->exists());
    }
}
