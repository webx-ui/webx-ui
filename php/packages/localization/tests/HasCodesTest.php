<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Localization\Tests\Fixtures\CodedPage;

/**
 * A code is brought to its shape on the model, whatever door it came in by, and a request that
 * checks it first sees the same code through `codeFor()`.
 */
final class HasCodesTest extends TestCase
{
    #[Test]
    public function a_typed_code_is_kept_in_the_shape_of_its_column(): void
    {

        $this->assertSame('tormoznye-kolodk', CodedPage::query()->create(['code' => 'Тормозные колодки'])->code);
        $this->assertSame('brake-pads', CodedPage::query()->create(['code' => 'Brake_Pads!'])->code);
        // Must start with a letter: a code of digits is put after the lead.
        $this->assertSame('page-2-columns', CodedPage::query()->create(['code' => '2 columns'])->code);
        // Nothing left: the model's check (or its fallback) has the last word.
        $this->assertSame('', CodedPage::query()->create(['code' => '!!!'])->code);
        $this->assertNull(CodedPage::query()->create(['code' => null])->code);
    }

    #[Test]
    public function an_untouched_code_is_left_as_it_was(): void
    {
        $page = CodedPage::query()->create(['code' => 'kept']);
        CodedPage::query()->whereKey($page->id)->update(['code' => 'Old_Shape']);

        $page = CodedPage::query()->findOrFail($page->id);
        $page->update(['title' => ['en' => 'Renamed']]);

        $this->assertSame('Old_Shape', $page->fresh()?->code);
    }

    #[Test]
    public function a_request_sees_the_code_that_will_be_kept(): void
    {
        $this->assertSame('main-menu', CodedPage::codeFor('code', 'Main menu'));
        $this->assertSame(['a'], CodedPage::codeFor('code', ['a']));
        $this->assertSame('Title', CodedPage::codeFor('title', 'Title'));
    }
}
