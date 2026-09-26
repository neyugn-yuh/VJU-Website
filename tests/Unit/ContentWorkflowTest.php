<?php

namespace Tests\Unit;

use App\Domain\Content\ContentStatus as S;
use App\Domain\Content\ContentWorkflow;
use App\Domain\SEO\RedirectResolver;
use App\Support\Slug;
use PHPUnit\Framework\TestCase;

class ContentWorkflowTest extends TestCase
{
    public function test_valid_and_invalid_edges(): void
    {
        $w = new ContentWorkflow;

        $this->assertTrue($w->isValidEdge(S::Draft, S::PendingReview));
        $this->assertTrue($w->isValidEdge(S::PendingReview, S::Published));
        $this->assertTrue($w->isValidEdge(S::Published, S::Draft));
        $this->assertTrue($w->isValidEdge(S::Scheduled, S::Published));
        $this->assertFalse($w->isValidEdge(S::Published, S::Scheduled));
        $this->assertFalse($w->isValidEdge(S::Trash, S::Published), 'trash only leaves through restore');
        $this->assertTrue($w->isValidEdge(S::Draft, S::Draft));
    }

    public function test_live_statuses(): void
    {
        $this->assertTrue(S::Published->isLive());
        $this->assertTrue(S::Scheduled->isLive());
        $this->assertFalse(S::PendingReview->isLive());
    }

    public function test_redirect_normalization(): void
    {
        $this->assertSame('/en/foo', RedirectResolver::normalize('https://vju.ac.vn/EN/Foo/?a=1#x'));
        $this->assertSame('/', RedirectResolver::normalize('https://vju.ac.vn'));
        $this->assertSame('/ja/日越大学', RedirectResolver::normalize('/ja/%E6%97%A5%E8%B6%8A%E5%A4%A7%E5%AD%A6/'));
    }

    public function test_slug_generation(): void
    {
        $this->assertSame('dai-hoc-viet-nhat', Slug::make('Đại học Việt Nhật', 'vi'));
        $this->assertSame('hoc-bong-2026', Slug::make('  Học bổng — 2026! ', 'vi'));
        $this->assertSame('日越大学', Slug::make('日越大学', 'ja'));
    }
}
