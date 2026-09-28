<?php

use App\Domain\Media\MediaService;
use App\Models\ContentTranslation;
use App\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The WordPress import leaves site.home_page_id empty, so the homepage falls back to a bare news grid
 * and align_homepage_with_crawl skipped itself. Point the setting at the migrated "trang-chu" page,
 * build its blocks, then use the same banner slides and card images as vju.ac.vn.
 */
return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('settings')->where('group', 'site')->where('name', 'home_page_id');

        if (json_decode((string) $setting->value('payload'), true)) {
            return;
        }

        $homeId = ContentTranslation::query()->where('locale', 'vi')->where('path', 'trang-chu')->value('content_id');

        if (! $homeId) {
            return;
        }

        $setting->update(['payload' => json_encode($homeId)]);

        (require __DIR__.'/2026_09_27_090000_align_homepage_with_crawl.php')->up();

        $ids = static fn (array $paths): array => array_values(array_filter(array_map(
            fn (string $path): ?int => Media::query()->where('path', "media/{$path}")->value('id'),
            $paths,
        )));
        $slides = $ids([
            '2026/09/anh-cover-website-1-scaled.png',
            '2026/09/anh-cover-website-8-scaled.webp',
            '2026/09/anh-cover-website-6-scaled.webp',
            '2026/09/bia-web-1-1.png',
            '2026/09/thiet-ke-chua-co-ten-6.png',
            '2026/09/img-2001-scaled.jpg',
            '2026/09/6.jpg',
        ]);
        $activities = $ids([
            '2026/09/sinh-vien-hoc-vien-vju-thuc-hanh-trong-phong-thi-nghiem-scaled.webp',
            '2026/09/520264090-1191593576346578-5830025539481119441-n-1.webp',
            '2026/09/img-9625-scaled.webp',
        ]);
        // The program card images were Elementor backgrounds, so the WordPress media import never saw them.
        $cards = array_map(
            fn (string $file): int => app(MediaService::class)->store(database_path("data/home/{$file}.jpeg"), ['source_system' => 'crawl'])->id,
            ['programmes-undergraduate', 'postgraduate', 'japanese_education'],
        );
        $programs = [
            ['title' => 'Đại học', 'text' => 'Chương trình cử nhân', 'url' => '/trang-chu/dao-tao/dai-hoc/'],
            ['title' => 'Thạc sĩ', 'text' => 'Chương trình sau đại học', 'url' => '/trang-chu/dao-tao/sau-dai-hoc/'],
            ['title' => 'Tiến sĩ', 'text' => 'Nghiên cứu chuyên sâu', 'url' => '/trang-chu/dao-tao/tien-si/'],
        ];

        foreach (ContentTranslation::query()->where('content_id', $homeId)->get() as $translation) {
            $translation->forceFill(['blocks' => array_map(fn (array $block): array => match ($block['type']) {
                'hero' => ['type' => 'hero', 'data' => [...$block['data'], 'slides' => array_map(fn (int $id): array => ['image_id' => $id], $slides)]],
                'programs' => ['type' => 'programs', 'data' => [...$block['data'], 'items' => array_map(fn (array $item, int $id): array => [...$item, 'image_id' => $id], $programs, $cards)]],
                'activity_gallery' => ['type' => 'activity_gallery', 'data' => [...$block['data'], 'items' => array_map(fn (int $id): array => ['image_id' => $id], $activities)]],
                default => $block,
            }, $translation->blocks ?? [])])->save();
        }
    }

    public function down(): void
    {
        // Homepage content is managed in the CMS; there is no safe generic rollback for editors' changes.
    }
};
