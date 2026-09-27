<?php

namespace Database\Seeders;

use App\Domain\Content\ContentService;
use App\Domain\Content\TaxonomyService;
use App\Domain\Media\MediaService;
use App\Domain\Menu\MenuService;
use App\Models\Category;
use App\Models\Content;
use App\Models\Menu;
use App\Models\Tag;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Database\Seeder;

/** Local demo data: one account per role (password "password"), taxonomy, posts, pages, menus. */
class DemoSeeder extends Seeder
{
    public function run(ContentService $content, TaxonomyService $taxonomy, MediaService $media, MenuService $menus): void
    {
        $users = [];
        foreach ([User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR, User::ROLE_CONTRIBUTOR] as $role) {
            $user = User::firstOrNew(['email' => strtolower($role).'@vju.test']);
            $user->fill(['name' => "Demo {$role}", 'password' => 'password'])->forceFill(['is_active' => true])->save();
            $user->syncRoles([$role]);
            $users[$role] = $user;
        }

        if (Content::exists()) {
            return;
        }

        $images = collect([[11, 61, 145], [196, 30, 58], [15, 118, 110], [180, 83, 9]])
            ->map(fn ($rgb, $i) => $media->store($this->image($rgb, "demo-{$i}"), ['alt' => 'Demo image '.($i + 1)], $users[User::ROLE_ADMIN]));

        $news = $taxonomy->saveCategory(new Category, null, ['vi' => ['name' => 'Tin tức', 'slug' => 'news-vn'], 'en' => ['name' => 'News', 'slug' => 'news'], 'ja' => ['name' => 'ニュース', 'slug' => 'news-ja']]);
        $children = collect([
            ['vi' => 'Tuyển sinh', 'en' => 'Admissions', 'ja' => '入学'],
            ['vi' => 'Đào tạo', 'en' => 'Academics', 'ja' => '教育'],
            ['vi' => 'Nghiên cứu', 'en' => 'Research', 'ja' => '研究'],
            ['vi' => 'Đời sống sinh viên', 'en' => 'Student life', 'ja' => '学生生活'],
        ])->map(fn ($names) => $taxonomy->saveCategory(new Category, $news->id, collect($names)->map(fn ($n) => ['name' => $n])->all()));
        $tag = $taxonomy->saveTag(new Tag, ['vi' => ['name' => 'Học bổng'], 'en' => ['name' => 'Scholarship'], 'ja' => ['name' => '奨学金']]);

        $lorem = '<p>Trường Đại học Việt Nhật (VJU) là trường đại học thành viên của Đại học Quốc gia Hà Nội, được thành lập trên cơ sở hợp tác giữa Chính phủ Việt Nam và Nhật Bản.</p><h2>Nội dung chính</h2><p>Chương trình đào tạo hướng tới sự phát triển bền vững với sự tham gia của các trường đại học hàng đầu Nhật Bản.</p><ul><li>Đào tạo liên ngành</li><li>Giảng viên Nhật Bản</li><li>Học bổng hấp dẫn</li></ul>';

        for ($i = 1; $i <= 12; $i++) {
            $category = $children[$i % 4];
            $translations = ['vi' => ['title' => "Tin tức VJU số {$i}: Hoạt động học thuật và hợp tác quốc tế", 'excerpt' => 'Tóm tắt bài viết demo cho hệ thống CMS mới của VJU.', 'body' => $lorem]];
            if ($i % 2 === 0) {
                $translations['en'] = ['title' => "VJU News #{$i}: Academic activities and international cooperation", 'excerpt' => 'Demo article summary.', 'body' => '<p>VNU Vietnam Japan University is a member university of Vietnam National University, Hanoi.</p>'];
            }
            if ($i % 4 === 0) {
                $translations['ja'] = ['title' => "日越大学ニュース {$i}", 'excerpt' => 'デモ記事の概要。', 'body' => '<p>日越大学はベトナム国家大学ハノイ校の構成大学です。</p>'];
            }

            $post = $content->save(new Content, [
                'type' => 'post', 'status' => 'published', 'published_at' => now()->subDays($i * 3),
                'featured_media_id' => $images[$i % 4]->id, 'is_commentable' => true,
                'categories' => [$category->id], 'tags' => $i % 3 === 0 ? [$tag->id] : [],
                'translations' => $translations,
            ], null);
            $post->forceFill(['author_id' => $users[$i % 2 ? User::ROLE_EDITOR : User::ROLE_AUTHOR]->id])->save();
        }

        $admissions = $content->save(new Content, [
            'type' => 'page', 'status' => 'published', 'template' => 'admissions',
            'translations' => [
                'vi' => ['title' => 'Tuyển sinh đại học', 'slug' => 'tuyen-sinh', 'excerpt' => 'Thông tin tuyển sinh hệ đại học chính quy.', 'blocks' => [
                    ['type' => 'hero', 'data' => ['heading' => 'Tuyển sinh đại học 2026', 'subheading' => 'Chương trình cử nhân chất lượng cao theo chuẩn Nhật Bản', 'slides' => [['image_id' => $images[0]->id]], 'buttons' => [['label' => 'Đăng ký ngay', 'url' => '#']]]],
                    ['type' => 'stats', 'data' => ['heading' => 'VJU trong con số', 'items' => [['value' => '9', 'label' => 'Chương trình cử nhân'], ['value' => '8', 'label' => 'Chương trình thạc sĩ'], ['value' => '100+', 'label' => 'Đối tác Nhật Bản']]]],
                    ['type' => 'steps', 'data' => ['heading' => 'Quy trình xét tuyển', 'items' => [['title' => 'Đăng ký trực tuyến', 'text' => 'Điền hồ sơ trên cổng tuyển sinh.'], ['title' => 'Nộp hồ sơ', 'text' => 'Nộp bản cứng các giấy tờ cần thiết.'], ['title' => 'Nhận kết quả', 'text' => 'Kết quả được công bố trên website.']]]],
                    ['type' => 'faq', 'data' => ['heading' => 'Câu hỏi thường gặp', 'items' => [['question' => 'Học phí bao nhiêu?', 'answer' => '<p>Xem chi tiết tại mục học phí.</p>'], ['question' => 'Có ký túc xá không?', 'answer' => '<p>Có, VJU hỗ trợ ký túc xá cho sinh viên.</p>']]]],
                ]],
                'en' => ['title' => 'Undergraduate admissions', 'slug' => 'admissions', 'blocks' => [
                    ['type' => 'hero', 'data' => ['heading' => 'Undergraduate admissions 2026', 'subheading' => 'High-quality bachelor programs with Japanese standards']],
                ]],
            ],
        ], null);

        $home = $content->save(new Content, [
            'type' => 'page', 'status' => 'published', 'template' => 'landing',
            'translations' => collect(['vi' => ['Trang chủ', 'Kiến tạo tương lai bền vững', 'Tin tức mới nhất'], 'en' => ['Home', 'Creating a sustainable future', 'Latest news'], 'ja' => ['ホーム', '持続可能な未来を創る', '最新ニュース']])
                ->map(fn ($s, $l) => ['title' => $s[0], 'slug' => "home-{$l}", 'blocks' => [
                    ['type' => 'hero', 'data' => ['heading' => $s[1], 'subheading' => 'VNU Vietnam Japan University', 'slides' => $images->map(fn ($image) => ['image_id' => $image->id])->all(), 'buttons' => [['label' => 'Tuyển sinh', 'url' => '/tuyen-sinh/']]]],
                    ['type' => 'intro', 'data' => ['heading' => 'Trường Đại học Việt Nhật', 'image_id' => $images[0]->id, 'body' => '<p><strong>Giới thiệu chung:</strong></p><p>Trường Đại học Việt Nhật (VNU-VJU) là trường đại học thành viên của Đại học Quốc gia Hà Nội, được thành lập trên cơ sở hợp tác giữa Việt Nam và Nhật Bản. Trường tập trung vào công nghệ kỹ thuật tiên tiến và khoa học liên ngành để phục vụ mục tiêu phát triển bền vững.</p>', 'youtube_id' => 'ZfEf8S7UAd8', 'buttons' => [['label' => 'Xem thêm', 'url' => '/about-vju-vn/message-from-the-rector-vn/']]]],
                    ['type' => 'programs', 'data' => ['heading' => 'Chương trình đào tạo', 'items' => [['title' => 'Đại học', 'text' => 'Chương trình cử nhân', 'image_id' => $images[1]->id, 'url' => '/trang-chu/dao-tao/dai-hoc/'], ['title' => 'Thạc sĩ', 'text' => 'Chương trình sau đại học', 'image_id' => $images[2]->id, 'url' => '/trang-chu/dao-tao/sau-dai-hoc/'], ['title' => 'Tiến sĩ', 'text' => 'Nghiên cứu chuyên sâu', 'image_id' => $images[3]->id, 'url' => '/trang-chu/dao-tao/tien-si/']]]],
                    ['type' => 'post_list', 'data' => ['heading' => $s[2], 'category_id' => $news->id, 'limit' => 6, 'style' => 'featured', 'more_url' => '/tin-tuc-va-su-kien/tin-tuc/']],
                    ['type' => 'activity_gallery', 'data' => ['heading' => 'Hoạt động', 'items' => [['title' => 'Học tập và nghiên cứu', 'image_id' => $images[0]->id], ['title' => 'Sinh viên VJU', 'image_id' => $images[1]->id], ['title' => 'Hoạt động quốc tế', 'image_id' => $images[2]->id], ['title' => 'Cộng đồng VJU', 'image_id' => $images[3]->id]]]],
                    ['type' => 'logos', 'data' => ['heading' => 'Đối tác đại học Nhật Bản', 'items' => $images->map(fn ($image, $i) => ['name' => ['Ibaraki', 'Osaka', 'Ritsumeikan', 'Tsukuba'][$i] ?? 'VJU partner', 'image_id' => $image->id])->all()]],
                    ['type' => 'contact', 'data' => ['heading' => 'Liên hệ', 'map_url' => 'https://maps.google.com/maps?q=Vietnam%20Japan%20University%20My%20Dinh&t=m&z=16&output=embed', 'address' => 'Trường Đại học Việt Nhật, đường Lưu Hữu Phước, Cầu Diễn, Nam Từ Liêm, Hà Nội.', 'address_hola' => 'Khu đô thị Đại học Quốc gia, Hòa Lạc, Thạch Thất, Hà Nội.', 'phone' => '(+84) 966 954 736 · (+84) 969 638 426 · 024.7306.6001', 'email' => 'vju@vnu.edu.vn']],
                ]])->all(),
        ], null);
        app(SiteSettings::class)->fill(['home_page_id' => $home->id])->save();

        foreach (['vi' => 'Trang chủ', 'en' => 'Home', 'ja' => 'ホーム'] as $locale => $homeLabel) {
            $menu = Menu::create(['name' => "Header {$locale}", 'location' => 'header', 'locale' => $locale]);
            $menus->sync($menu, [
                ['label' => $homeLabel, 'item_type' => 'custom', 'url' => $locale === 'vi' ? '/' : "/{$locale}/"],
                ['label' => $news->translation($locale)?->name ?? 'News', 'item_type' => 'category', 'category_id' => $news->id, 'children' => $children->map(fn ($c) => ['label' => $c->translations()->where('locale', $locale)->value('name') ?? $c->slug, 'item_type' => 'category', 'category_id' => $c->id])->all()],
                ['label' => $locale === 'vi' ? 'Tuyển sinh' : 'Admissions', 'item_type' => 'content', 'content_id' => $admissions->id],
                ['label' => 'VNU', 'item_type' => 'external_url', 'url' => 'https://vnu.edu.vn', 'target' => '_blank'],
            ]);
        }
    }

    private function image(array $rgb, string $name): string
    {
        $img = imagecreatetruecolor(1600, 900);
        imagefill($img, 0, 0, imagecolorallocate($img, ...$rgb));
        for ($i = 0; $i < 12; $i++) {
            imagefilledellipse($img, random_int(0, 1600), random_int(0, 900), 400, 400, imagecolorallocatealpha($img, 255, 255, 255, 110));
        }
        $path = sys_get_temp_dir()."/{$name}.jpg";
        imagejpeg($img, $path, 85);

        return $path;
    }
}
