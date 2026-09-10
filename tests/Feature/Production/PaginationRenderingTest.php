<?php

namespace Tests\Feature\Production;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class PaginationRenderingTest extends TestCase
{
    public function test_numbered_pagination_is_persian_bootstrap_and_keeps_filters(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 15), 40, 15, 2, ['path' => '/users']);
        $html = $paginator->appends(['company' => 4])->links()->toHtml();
        $this->assertStringContainsString('pagination-sm', $html);
        $this->assertStringContainsString('نمایش 16 تا 30 از 40 مورد', $html);
        $this->assertStringContainsString('قبلی', $html);
        $this->assertStringContainsString('بعدی', $html);
        $this->assertStringContainsString('company=4', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_simple_pagination_and_single_page_render_safely(): void
    {
        $html = (new Paginator(range(1, 16), 15, 1))->links()->toHtml();
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('rel="next"', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertSame('', trim((new LengthAwarePaginator([1], 1, 15))->links()->toHtml()));
        $last = (new LengthAwarePaginator(range(1, 10), 40, 15, 3))->links()->toHtml();
        $this->assertStringNotContainsString('rel="next"', $last);
    }
}
