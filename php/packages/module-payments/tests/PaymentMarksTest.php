<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Payments\Doctor\PaymentMarksCheck;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodOption;
use WebxUi\Payments\Methods;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\PaymentMark;
use WebxUi\Payments\PaymentMarks;
use WebxUi\Payments\Tests\Checkout\PaymentsCheckoutCase;
use WebxUi\Payments\Tests\Fakes\FakeOnlineType;
use WebxUi\Payments\Tests\Fakes\MarkedType;

/** The marks of the payment systems (groups spec §3): the registry, the files, the plates. */
final class PaymentMarksTest extends PaymentsCheckoutCase
{
    private const string FILES = __DIR__.'/../resources/marks/';

    /** @var list<string> files a test put in the place of a mark */
    private array $swapped = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), $this->swapped);

        parent::tearDown();
    }

    #[Test]
    public function the_registry_keeps_the_order_of_the_page(): void
    {
        $this->assertSame(['visa', 'mastercard', 'apple-pay', 'google-pay', 'privat24', 'privatbank', 'mono'], $this->marks()->keys());
    }

    #[Test]
    public function known_keeps_the_order_drops_repeats_and_what_nobody_registered(): void
    {
        $this->assertSame(['visa', 'google-pay'], $this->marks()->known(['google-pay', 'nobody', 'visa', 'google-pay']));
    }

    #[Test]
    public function a_satellite_adds_its_own_and_a_key_registered_again_keeps_its_place(): void
    {
        $marks = $this->marks();
        $marks->register('wallet-x', 'Wallet X');
        $marks->register('privat24', 'Privat24', self::FILES.'visa.svg');

        $this->assertSame(['privat24', 'wallet-x'], $marks->known(['wallet-x', 'privat24']));
        $this->assertFalse($marks->get('privat24')->isPlate());
    }

    /** Says only what holds for any file: an official one comes with its own paths and proportions. */
    #[Test]
    public function every_file_is_drawn_at_the_width_it_states_itself(): void
    {
        foreach (['visa', 'mastercard', 'apple-pay', 'google-pay'] as $key) {
            $mark = $this->marks()->get($key);

            $this->assertFalse($mark->isPlate(), $key);
            $this->assertFileExists((string) $mark->file, $key);
            $this->assertSame(PaymentMark::widthOf((string) file_get_contents((string) $mark->file)), $mark->width, $key);
            $this->assertStringContainsString('width="'.$mark->width.'" height="24"', $this->render($key), $key);
        }
    }

    #[Test]
    public function the_notice_says_whose_the_marks_are_and_claims_no_licence_for_them(): void
    {
        $notice = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(self::FILES.'NOTICE.md'));

        $this->assertStringContainsString('belong to their owners and are not covered by the MIT licence', $notice);
        $this->assertStringNotContainsString('CC0', $notice);
    }

    #[Test]
    public function a_file_is_an_image_with_alt_and_a_mark_without_one_a_plate(): void
    {
        $this->visaFile('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 76 24"/>');

        $file = $this->render('visa');

        $this->assertStringContainsString('<img class="webx-payments__mark"', $file);
        $this->assertStringContainsString('width="76" height="24" alt="Visa"', $file);
        $this->assertStringContainsString('/webx/payments/marks/visa.svg?v=', $file);

        $this->assertSame('<span class="webx-payments__mark is-text">Приват24</span>', trim($this->render('privat24')));
    }

    #[Test]
    public function a_mark_nobody_registered_is_a_plate_and_not_an_error(): void
    {
        $this->assertSame('<span class="webx-payments__mark is-text">wallet-x</span>', trim($this->render('wallet-x')));
    }

    #[Test]
    public function a_file_that_went_missing_is_a_plate_and_not_an_error(): void
    {
        $this->marks()->register('visa', 'Visa', self::FILES.'gone.svg');

        $this->assertSame('<span class="webx-payments__mark is-text">Visa</span>', trim($this->render('visa')));
        $this->get('/webx/payments/marks/visa.svg')->assertNotFound();
    }

    #[Test]
    public function a_file_answers_with_a_cache_for_good(): void
    {
        $mark = $this->marks()->get('visa');
        $response = $this->get((string) $mark->url());

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=31536000', (string) $response->headers->get('Cache-Control'));
        $this->assertSame((string) file_get_contents((string) $mark->file), $response->getContent());
    }

    #[Test]
    public function a_plate_and_a_stranger_have_no_file(): void
    {
        $this->get('/webx/payments/marks/privat24.svg')->assertNotFound();
        $this->get('/webx/payments/marks/nobody.svg')->assertNotFound();
    }

    /** The promise of the task: an official file takes the place of ours, and no code moves. */
    #[Test]
    public function another_file_with_other_paths_and_proportions_needs_no_code(): void
    {
        $this->visaFile('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 38 24"><rect width="38" height="24"/></svg>');
        $before = $this->marks()->get('visa');
        $official = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 48"><g><circle cx="60" cy="24" r="20"/></g></svg>';

        $this->visaFile($official);

        $after = $this->marks()->get('visa');

        $this->assertSame(60, $after->width);
        $this->assertNotSame($before->version, $after->version);
        $this->assertStringContainsString('width="60" height="24"', $this->render('visa'));
        $this->assertSame($official, $this->get((string) $after->url())->getContent());
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function files(): array
    {
        return [
            'a viewBox' => ['<svg viewBox="0 0 76 24"></svg>', 76],
            'commas and an origin' => ['<svg viewBox="-1,-1,19,12"/>', 38],
            'the viewBox over the sizes' => ['<svg width="10" height="10" viewBox="0 0 48 24"/>', 48],
            'only the sizes' => ["<svg width='24' height='12'/>", 48],
            'nothing said' => ['<svg/>', 38],
            'a box of nothing' => ['<svg viewBox="0 0 0 0"/>', 38],
            'not a picture' => ['hello', 38],
        ];
    }

    #[Test]
    #[DataProvider('files')]
    public function the_width_comes_from_the_file(string $svg, int $width): void
    {
        $this->assertSame($width, PaymentMark::widthOf($svg));
    }

    /** @return array<string, array{0: list<string>, 1: list<string>, 2: bool}> */
    public static function applePay(): array
    {
        return [
            'only Apple Pay' => [['apple-pay'], ['apple-pay'], true],
            'only Google Pay' => [['google-pay'], ['google-pay'], false],
            'both wallets' => [['google-pay', 'apple-pay'], ['apple-pay', 'google-pay'], false],
            'a card with Apple Pay' => [['apple-pay', 'visa', 'mastercard'], ['visa', 'mastercard', 'apple-pay'], false],
            'nothing' => [[], [], false],
            'a stranger is skipped' => [['nobody'], [], false],
        ];
    }

    /**
     * @param  list<string>  $marks
     * @param  list<string>  $expected
     */
    #[Test]
    #[DataProvider('applePay')]
    public function an_option_carries_its_marks_and_only_apple_pay_follows_from_them(array $marks, array $expected, bool $only): void
    {
        $this->registerType();
        $option = $this->option(self::method('m', $marks));

        $this->assertSame($expected, $option->marks);
        $this->assertSame($only, $option->applePayOnly);
        $this->assertSame($expected, $option->toArray()['marks']);
        $this->assertSame($only, $option->toArray()['apple_pay_only']);
    }

    #[Test]
    public function a_type_without_the_contract_shows_no_marks(): void
    {
        $this->assertSame([], $this->marks()->of(self::method('m', ['visa']), new FakeOnlineType));
    }

    #[Test]
    public function a_group_shows_the_marks_of_its_methods_once_in_the_order_of_the_registry(): void
    {
        $this->registerType();

        $views = $this->app->make(Methods::class)->grouped([
            $this->option(self::method('a', ['google-pay', 'mastercard'])),
            $this->option(self::method('b', ['privat24', 'visa', 'mastercard'])),
        ]);

        $this->assertCount(1, $views);
        $this->assertSame(['visa', 'mastercard', 'google-pay', 'privat24'], $views[0]->marks);
    }

    #[Test]
    public function the_doctor_names_a_mark_nobody_registered(): void
    {
        $this->assertContains(PaymentMarksCheck::class, $this->app->make(DoctorChecks::class)->all());

        $this->registerType();
        PaymentMethod::query()->update(['enabled' => false]);
        PaymentMethod::query()->create(['key' => 'marked', 'type' => MarkedType::KEY, 'title' => ['en' => 'Marked'], 'enabled' => true, 'position' => 1, 'delivery_rule' => ['mode' => 'any'], 'settings' => ['marks' => ['visa', 'nobody']]]);

        $found = $this->app->make(PaymentMarksCheck::class)->run();

        $this->assertCount(1, $found);
        $this->assertInstanceOf(Diagnosis::class, $found[0]);
        $this->assertStringContainsString('Method marked names the mark nobody', $found[0]->detail);
    }

    #[Test]
    public function the_doctor_is_quiet_about_the_marks_it_knows(): void
    {
        $this->assertSame([], $this->app->make(PaymentMarksCheck::class)->run());
    }

    /** Puts a file of known content in the place of Visa: what a test expects must not come from ours. */
    private function visaFile(string $svg): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'mark');
        file_put_contents($file, $svg);

        $this->swapped[] = $file;
        $this->marks()->register('visa', 'Visa', $file);
    }

    private function marks(): PaymentMarks
    {
        return $this->app->make(PaymentMarks::class);
    }

    private function render(string $key): string
    {
        return Blade::render('<x-webx-payments::mark :name="$key" />', ['key' => $key]);
    }

    private function registerType(): void
    {
        $this->app->make(MethodTypes::class)->register(new MarkedType);
    }

    private function option(Method $method): MethodOption
    {
        return $this->app->make(Methods::class)->option($method, new PaymentContext(1000, 'EUR', 'en', null, Purpose::Checkout));
    }

    /** @param  list<string>  $marks */
    private static function method(string $key, array $marks): Method
    {
        return new Method($key, MarkedType::KEY, [], [], null, true, 1, ['mode' => 'any'], null, null, ['marks' => $marks], false);
    }
}
