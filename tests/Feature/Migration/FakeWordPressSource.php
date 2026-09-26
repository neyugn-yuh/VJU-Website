<?php

namespace Tests\Feature\Migration;

use App\Domain\Migration\WordPress\Sources\WordPressSource;

/** In-memory source with the normalized record shapes documented on WordPressSource. */
class FakeWordPressSource implements WordPressSource
{
    public function __construct(public array $data = []) {}

    public function name(): string
    {
        return 'fake';
    }

    public function users(): iterable
    {
        return $this->data['users'] ?? [];
    }

    public function terms(string $taxonomy): iterable
    {
        return array_filter($this->data['terms'] ?? [], fn ($t) => $t['taxonomy'] === $taxonomy);
    }

    public function media(array $filters = []): iterable
    {
        return array_filter($this->data['media'] ?? [], fn ($m) => empty($filters['id']) || $m['id'] === $filters['id']);
    }

    public function posts(string $type, array $filters = []): iterable
    {
        return array_values(array_filter($this->data['posts'] ?? [], fn ($p) => $p['type'] === $type
            && (empty($filters['id']) || $p['id'] === $filters['id'])
            && (empty($filters['locale']) || $p['lang'] === $filters['locale'])));
    }

    public function menus(): iterable
    {
        return $this->data['menus'] ?? [];
    }

    public function comments(array $filters = []): iterable
    {
        return $this->data['comments'] ?? [];
    }

    public function inventory(): array
    {
        return ['source' => 'fake', 'counts' => []];
    }

    public static function post(array $overrides): array
    {
        return $overrides + [
            'type' => 'post', 'status' => 'publish', 'lang' => 'vi', 'translations' => [], 'parent' => 0,
            'excerpt' => null, 'content' => '<p>Nội dung</p>', 'content_format' => 'rendered', 'author' => 0,
            'date_gmt' => '2025-05-01T03:00:00', 'modified_gmt' => '2025-05-02T03:00:00', 'featured_media' => 0,
            'categories' => [], 'tags' => [], 'comment_status' => 'closed', 'menu_order' => 0, 'template' => '',
            'seo' => null, 'meta' => [],
        ];
    }
}
