<?php

namespace Tests\Feature\Media;

use App\Domain\Media\MediaService;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_image_upload_stores_metadata_and_generates_derivatives(): void
    {
        $media = app(MediaService::class)->store(UploadedFile::fake()->image('Ảnh Khuôn viên.jpg', 2400, 1200), ['alt' => 'Khuôn viên']);

        $media->refresh();
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame([2400, 1200], [$media->width, $media->height]);
        $this->assertSame(64, strlen($media->checksum));
        $this->assertStringStartsWith('media/'.now()->format('Y/m').'/anh-khuon-vien', $media->path);
        Storage::disk('public')->assertExists($media->path);

        // Queue is sync in tests: derivatives exist and are downscaled.
        $this->assertSame(1600, $media->metadata['derivatives']['web']['width']);
        $this->assertSame(400, $media->metadata['derivatives']['thumb']['width']);
        Storage::disk('public')->assertExists($media->metadata['derivatives']['webp']['path']);
    }

    public function test_mime_is_detected_from_content_not_extension(): void
    {
        $fake = UploadedFile::fake()->createWithContent('photo.jpg', "<?php system(\$_GET['c']); ?>");

        $this->expectException(ValidationException::class);
        app(MediaService::class)->store($fake);
    }

    public function test_svg_is_stored_only_after_active_content_is_removed(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)"><script>alert(2)</script>'
            .'<foreignObject><div>x</div></foreignObject><a xlink:href="javascript:alert(3)"><rect width="10" height="10" onclick="alert(4)"/></a>'
            .'<image href="https://evil.example/x.png"/><image href="data:image/png;base64,iVBORw0KGgo="/><path d="M0 0h10"/></svg>');

        $media = app(MediaService::class)->store($svg);
        $stored = Storage::disk('public')->get($media->path);

        $this->assertSame('image/svg+xml', $media->mime_type);
        foreach (['<script', 'onload', 'onclick', 'foreignObject', 'javascript:', 'evil.example'] as $bad) {
            $this->assertStringNotContainsString($bad, $stored, $bad);
        }
        $this->assertStringContainsString('<path d="M0 0h10"/>', $stored);
        $this->assertStringContainsString('data:image/png', $stored, 'embedded raster images are kept');
    }

    public function test_svg_with_entities_is_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('bomb.svg', '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "aaaa">]><svg xmlns="http://www.w3.org/2000/svg">&a;</svg>');

        $this->expectException(ValidationException::class);
        app(MediaService::class)->store($svg);
    }

    public function test_duplicate_checksum_reuses_existing_media(): void
    {
        $service = app(MediaService::class);
        $file = UploadedFile::fake()->image('a.png', 100, 100);
        $copy = tempnam(sys_get_temp_dir(), 'dup');
        copy($file->getRealPath(), $copy);

        $first = $service->store($file);
        $second = $service->store(new UploadedFile($copy, 'b.png', null, null, true));

        $this->assertTrue($first->is($second));
        $this->assertTrue($service->lastWasDuplicate);
        $this->assertSame(1, Media::count());
    }

    public function test_files_are_never_overwritten(): void
    {
        $service = app(MediaService::class);
        $a = $service->store(UploadedFile::fake()->image('same.jpg', 10, 10));
        $b = $service->store(UploadedFile::fake()->image('same.jpg', 20, 20));

        $this->assertNotSame($a->path, $b->path);
    }

    public function test_pdf_upload(): void
    {
        $pdf = UploadedFile::fake()->createWithContent('quy-che.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        $media = app(MediaService::class)->store($pdf);

        $this->assertSame('application/pdf', $media->mime_type);
        $this->assertNull($media->width);
    }
}
