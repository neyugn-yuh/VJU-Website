<?php

use App\Models\ContentTranslation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * align_homepage_with_crawl wrote Vietnamese copy into every locale's homepage. Replace the English
 * and Japanese intro, program cards and contact details with the text of vju.ac.vn/en and /ja.
 */
return new class extends Migration
{
    public function up(): void
    {
        $homeId = json_decode((string) DB::table('settings')->where('group', 'site')->where('name', 'home_page_id')->value('payload'), true);

        if (! $homeId) {
            return;
        }

        $address = 'Luu Huu Phuoc Road, My Dinh 1 Residential Area, Cau Dien Ward, Nam Tu Liem District, Hanoi, Vietnam';
        $phone = '(+84) 966 954 736 · (+84) 969 638 426 · 024.7306.6001';
        $copy = [
            'en' => [
                'hero' => ['buttons' => [['label' => 'Admissions', 'url' => '/en/admissions/']]],
                'intro' => [
                    'heading' => 'About VJU',
                    'body' => '<p><strong>Introduction:</strong></p><p>Vietnam Japan University (VJU) is the seventh member university under the Vietnam National University, Hanoi (VNU). It was established in 2014 by the Decree No. 1186/QĐ-TTg dated July 21, 2014 of the Vietnamese Prime Minister, following a joint declaration issued by Vietnamese and Japanese governments. VJU is a research-oriented university, focus on advanced technologies and interdisciplinary sciences meeting the sustainable development goals.</p>',
                    'buttons' => [['label' => 'Read more', 'url' => '/en/about-vju/message-from-the-rector/']],
                ],
                'programs' => ['heading' => 'Education', 'items' => [
                    ['title' => 'Undergraduate Programs', 'text' => '', 'url' => '/en/academics/undergraduate/'],
                    ['title' => 'Master Programs', 'text' => '', 'url' => '/en/academics/post-graduate/'],
                    ['title' => 'PhD Programs', 'text' => '', 'url' => '/en/phdprogram/'],
                ]],
                'contact' => ['address' => $address, 'address_hola' => 'Vietnam Japan University, Hoa Lac, Thach That District, Ha Noi, Vietnam', 'phone' => $phone, 'email' => 'admission@vju.ac.vn'],
            ],
            'ja' => [
                'hero' => ['buttons' => [['label' => '入試・入学案内', 'url' => '/en/admissions/']]],
                'intro' => [
                    'heading' => '大学の理念・目標',
                    'body' => '<p><strong>ミッション：</strong></p><ul><li>ベトナム、日本、東南アジアをはじめ、世界で活躍できる次世代のリーダー、マネージャーやエキスパートを目指す高度人材を育成します。</li><li>持続可能な開発のために、最新のテクノロジーと学際科学における研究成果を生み出し、ベトナムと日本の間の知識移転を促進し社会に貢献します。</li><li>ベトナム国家大学ハノイ校（VNU-HN）のミッションにコミットし、ベトナムの高度教育制度を改善します。</li><li>ベトナムと日本の友好関係を促進します。</li></ul><p><strong>2035年に向けたビジョン：</strong></p><p>持続可能な発展のために日越両国の優位性を活用し、先端技術と学際科学の分野で、アジアにおいて主要な研究志向大学を目指します。</p><p><strong>教育理念：</strong></p><ul><li>リベラルアーツとサステイナビリティサイエンス</li></ul>',
                    'buttons' => [['label' => '続きを読む', 'url' => '/ja/about-vju-jp/message-from-the-rector-jp/']],
                ],
                'programs' => ['heading' => 'プログラム', 'items' => [
                    ['title' => '学部', 'text' => '', 'url' => '/en/academics/undergraduate/'],
                    ['title' => '大学院', 'text' => '', 'url' => '/en/academics/post-graduate/'],
                    ['title' => '日本語教育', 'text' => ''],
                ]],
                'news' => ['heading' => 'ニュースとイベント'],
                'activities' => ['heading' => 'インターンシップとフィールドトリップ'],
                'contact' => ['heading' => '連絡先情報', 'address' => $address, 'address_hola' => 'Vietnam - Japan National University, Hoa Lac, Thach That District, Ha Noi, Vietnam', 'phone' => $phone, 'email' => 'admission@vju.ac.vn'],
            ],
        ];
        $keys = ['hero' => 'hero', 'intro' => 'intro', 'programs' => 'programs', 'post_list' => 'news', 'activity_gallery' => 'activities', 'contact' => 'contact'];

        foreach (ContentTranslation::query()->where('content_id', $homeId)->whereIn('locale', array_keys($copy))->get() as $translation) {
            $text = $copy[$translation->locale];
            $translation->forceFill(['blocks' => array_map(function (array $block) use ($text, $keys): array {
                $patch = $text[$keys[$block['type']] ?? ''] ?? null;

                if (! $patch) {
                    return $block;
                }

                // Program cards keep their images; only the copy and links are replaced.
                if (isset($patch['items'])) {
                    $patch['items'] = array_map(
                        fn (array $item, ?array $old): array => [...$item, 'image_id' => $old['image_id'] ?? null],
                        $patch['items'],
                        array_pad(array_slice($block['data']['items'] ?? [], 0, count($patch['items'])), count($patch['items']), null),
                    );
                }

                return ['type' => $block['type'], 'data' => [...$block['data'], ...$patch]];
            }, $translation->blocks ?? [])])->save();
        }
    }

    public function down(): void
    {
        // Homepage content is managed in the CMS; there is no safe generic rollback for editors' changes.
    }
};
