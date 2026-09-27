<?php

use App\Models\ContentTranslation;
use App\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $payload = DB::table('settings')->where('group', 'site')->where('name', 'home_page_id')->value('payload');
        $homeId = json_decode((string) $payload, true);

        if (! $homeId) {
            return;
        }

        $files = [
            'hero' => ['anh-cover-website-1-scaled.png', 'anh-cover-website-8-scaled.webp', 'anh-cover-website-6-scaled.webp', 'anh-cover-website-scaled.png', 'anh-cover-website-2-scaled.png'],
            'rector' => ['big-pic-gsts-furuta-motoo-hieu-truong-truong-dai-hoc-viet-nhat-1-768x1002.jpg'],
            'programs' => ['img-6791.jpg', 'hoi-thao-web-vi.png', 'anh-cover-website-2-scaled.png'],
            'activities' => ['sinh-vien-hoc-vien-vju-thuc-hanh-trong-phong-thi-nghiem-scaled.webp', '520264090-1191593576346578-5830025539481119441-n-1.webp', 'img-9625-scaled.webp', 'img-6791.jpg'],
            'logos' => ['ibaraki-e1735182148168.png', 'osaka-e1735182159662.png', 'ritsumeikan-e1735182174610.png', 'tsukuba-e1735182188913.png', 'ynu-1-e1735182266423.png', 'waseda-e1735182255881.png', 'ut-e1735182242962.png'],
        ];

        $media = Media::query()->whereIn('filename', collect($files)->flatten()->unique())->get()->keyBy('filename');
        $id = static fn (string $filename): ?int => $media->get($filename)?->id;
        $ids = static fn (string $group): array => array_values(array_filter(array_map($id, $files[$group] ?? [])));
        $hero = $ids('hero');

        if (! $hero) {
            return;
        }

        $rector = $id($files['rector'][0]);
        $programs = $ids('programs');
        $activities = $ids('activities');
        $logos = $ids('logos');

        $translations = [
            'vi' => ['intro' => 'Trường Đại học Việt Nhật', 'programs' => 'Chương trình đào tạo', 'news' => 'Tin tức & sự kiện', 'activities' => 'Hoạt động', 'partners' => 'Đối tác đại học Nhật Bản', 'contact' => 'Liên hệ'],
            'en' => ['intro' => 'Vietnam Japan University', 'programs' => 'Academic programs', 'news' => 'News & events', 'activities' => 'Activities', 'partners' => 'Japanese university partners', 'contact' => 'Contact'],
            'ja' => ['intro' => 'ベトナム日本大学', 'programs' => '教育プログラム', 'news' => 'ニュースとイベント', 'activities' => '活動', 'partners' => '日本の大学パートナー', 'contact' => 'お問い合わせ'],
        ];

        foreach (ContentTranslation::query()->where('content_id', $homeId)->get() as $translation) {
            $label = $translations[$translation->locale] ?? $translations['vi'];
            $programItems = array_values(array_filter([
                ['title' => 'Đại học', 'text' => 'Chương trình cử nhân', 'image_id' => $programs[0] ?? null, 'url' => '/trang-chu/dao-tao/dai-hoc/'],
                ['title' => 'Thạc sĩ', 'text' => 'Chương trình sau đại học', 'image_id' => $programs[1] ?? null, 'url' => '/trang-chu/dao-tao/sau-dai-hoc/'],
                ['title' => 'Tiến sĩ', 'text' => 'Nghiên cứu chuyên sâu', 'image_id' => $programs[2] ?? null, 'url' => '/trang-chu/dao-tao/tien-si/'],
            ], fn (array $item): bool => (bool) $item['image_id']));
            $activityItems = array_map(fn (int $imageId): array => ['image_id' => $imageId], $activities);
            $logoItems = array_map(fn (int $imageId): array => ['image_id' => $imageId, 'name' => 'VJU partner'], $logos);

            $translation->forceFill(['blocks' => [
                ['type' => 'hero', 'data' => ['heading' => $translation->locale === 'vi' ? 'Kiến tạo tương lai bền vững' : 'Creating a sustainable future', 'subheading' => 'VNU Vietnam Japan University', 'slides' => array_map(fn (int $imageId): array => ['image_id' => $imageId], $hero), 'buttons' => [['label' => $translation->locale === 'vi' ? 'Tuyển sinh' : 'Admissions', 'url' => '/tuyen-sinh/']]]],
                ['type' => 'intro', 'data' => ['heading' => $label['intro'], 'image_id' => $rector, 'body' => '<p><strong>Giới thiệu chung:</strong></p><p>Trường Đại học Việt Nhật (VNU-VJU) là trường đại học thành viên của Đại học Quốc gia Hà Nội, được thành lập trên cơ sở hợp tác giữa Việt Nam và Nhật Bản. Trường tập trung vào công nghệ kỹ thuật tiên tiến và khoa học liên ngành để phục vụ mục tiêu phát triển bền vững.</p>', 'youtube_id' => 'ZfEf8S7UAd8', 'buttons' => [['label' => 'Xem thêm', 'url' => '/about-vju-vn/message-from-the-rector-vn/']]]],
                ['type' => 'programs', 'data' => ['heading' => $label['programs'], 'items' => $programItems]],
                ['type' => 'post_list', 'data' => ['heading' => $label['news'], 'limit' => 6, 'style' => 'featured', 'more_url' => '/tin-tuc-va-su-kien/tin-tuc/']],
                ['type' => 'activity_gallery', 'data' => ['heading' => $label['activities'], 'items' => $activityItems]],
                ['type' => 'logos', 'data' => ['heading' => $label['partners'], 'items' => $logoItems]],
                ['type' => 'contact', 'data' => ['heading' => $label['contact'], 'map_url' => 'https://maps.google.com/maps?q=Vietnam%20Japan%20University%20My%20Dinh&t=m&z=16&output=embed', 'address' => 'Trường Đại học Việt Nhật, đường Lưu Hữu Phước, Cầu Diễn, Nam Từ Liêm, Hà Nội.', 'address_hola' => 'Khu đô thị Đại học Quốc gia, Hòa Lạc, Thạch Thất, Hà Nội.', 'phone' => '(+84) 966 954 736 · (+84) 969 638 426 · 024.7306.6001', 'email' => 'vju@vnu.edu.vn']],
            ]])->save();
        }
    }

    public function down(): void
    {
        // Homepage content is managed in the CMS; there is no safe generic rollback for editors' changes.
    }
};
