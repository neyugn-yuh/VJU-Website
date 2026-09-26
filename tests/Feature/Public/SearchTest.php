<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** InnoDB FULLTEXT only sees committed rows, so this class truncates instead of using transactions. */
class SearchTest extends TestCase
{
    use DatabaseTruncation;

    /** Seeded/config tables survive truncation. */
    protected $exceptTables = ['migrations', 'settings', 'roles', 'permissions', 'role_has_permissions'];

    public function test_fulltext_search_per_locale_including_japanese(): void
    {
        $this->makeContent(['translations' => [
            'vi' => ['title' => 'Học bổng toàn phần cho sinh viên', 'body' => '<p>Chương trình học bổng</p>'],
            'ja' => ['title' => '奨学金のお知らせ', 'body' => '<p>日越大学の奨学金プログラム</p>'],
        ]]);
        $this->makeContent(['translations' => ['vi' => ['title' => 'Lịch thi học kỳ']]]);

        $this->visit('/search/?q='.urlencode('học bổng'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Search')->has('items', 1)->where('items.0.title', 'Học bổng toàn phần cho sinh viên'));

        $this->visit('/ja/search/?q='.urlencode('奨学金'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('items', 1)->where('items.0.title', '奨学金のお知らせ'));

        $this->visit('/en/search/?q='.urlencode('học bổng'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('items', 0));
    }
}
