<?php

declare(strict_types=1);

namespace WebxUi\Faq\Tests;

use PHPUnit\Framework\Attributes\Test;

/**
 * The panel's language is the interface's, not the content's: a panel read in a language the site
 * has no content in still lists the questions in the site's own words.
 */
final class ContentLanguageTest extends TestCase
{
    #[Test]
    public function the_list_is_in_the_content_language_whatever_the_interface_speaks(): void
    {
        $this->question('Do you take cards?', 'Принимаете карты?', categories: [$this->category('Billing')]);

        // The interface in German, which this site has no content in.
        $response = $this->actingAs($this->editor(), 'cms')->withHeader('X-Webx-Locale', 'de')->getJson($this->api())->assertOk();

        $this->assertSame('Do you take cards?', $response->json('data.0.question'));
        $this->assertSame('Billing', $response->json('filters.categories.0.title'));

        // In a language the site has, that one.
        $this->assertSame('Принимаете карты?', $this->actingAs($this->editor(), 'cms')->withHeader('X-Webx-Locale', 'ru')->getJson($this->api())->json('data.0.question'));
    }
}
