<?php

use App\Models\Content;
use App\Models\Media;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $images = Media::query()
            ->whereIn('filename', ['hoi-thao-web-vi.png', 'img-6791.jpg', '2508-vietnhat-935x528567891-1.jpg'])
            ->get()
            ->keyBy('filename');

        $imageIds = array_values(array_filter([
            $images->get('hoi-thao-web-vi.png')?->id,
            $images->get('img-6791.jpg')?->id,
            $images->get('2508-vietnhat-935x528567891-1.jpg')?->id,
        ]));

        if (! $imageIds) {
            return;
        }

        $posts = Content::query()
            ->where('type', 'post')
            ->whereHas('translations', fn ($query) => $query->where('locale', 'vi')->where('title', 'like', 'Tin tức VJU số %'))
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        foreach ($posts as $index => $post) {
            $post->forceFill(['featured_media_id' => $imageIds[$index % count($imageIds)]])->saveQuietly();
        }
    }

    public function down(): void
    {
        // The previous featured image belongs to demo data and is not stable across environments.
    }
};
