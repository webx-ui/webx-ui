<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use PHPUnit\Framework\Attributes\Test;

/**
 * Duties, requirements, what we offer (decision 18): one list, the languages inside each line —
 * saved and read back as sent, and printed in a language only where a line is written in it.
 */
final class ListsTest extends TestCase
{
    #[Test]
    public function the_languages_travel_inside_the_lines_there_and_back(): void
    {
        $this->useLocales('en', 'uk');

        $vacancy = $this->vacancy('designer', attributes: ['slug' => ['en' => 'designer', 'uk' => 'dyzainer'], 'title' => ['en' => 'Designer', 'uk' => 'Дизайнер']]);
        $lines = [
            ['text' => ['en' => 'Draw', 'uk' => 'Малювати']],
            ['text' => ['en' => 'Think']],
            ['text' => ['uk' => 'Лише українською']],
        ];

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($vacancy->id), ['values' => ['benefits' => $lines]])
            ->assertOk()
            ->assertJsonPath('data.values.benefits', $lines);

        $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/publish'))->assertOk();

        $this->assertSame($lines, $this->actingAs($this->editor(), 'cms')->getJson($this->api($vacancy->id))->json('data.values.benefits'));

        $english = (string) $this->get('/careers/designer')->assertOk()->getContent();
        $this->assertStringContainsString('<li>Draw</li>', $english);
        $this->assertStringContainsString('<li>Think</li>', $english);
        $this->assertStringNotContainsString('Лише українською', $english);

        $ukrainian = (string) $this->get('/uk/careers/dyzainer')->assertOk()->getContent();
        $this->assertStringContainsString('<li>Малювати</li>', $ukrainian);
        $this->assertStringContainsString('<li>Лише українською</li>', $ukrainian);
        $this->assertStringNotContainsString('Think', $ukrainian);
    }
}
