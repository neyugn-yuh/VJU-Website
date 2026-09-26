<?php

namespace Tests;

use App\Domain\Content\ContentService;
use App\Models\Content;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * GET that keeps the trailing slash. Laravel's test client trims it, but public canonical URLs
     * end with "/" (WordPress style) and the resolver redirects slash-less URLs.
     */
    protected function visit(string $uri): TestResponse
    {
        $request = Request::create('http://localhost'.$uri, 'GET', server: $this->serverVariables);
        $response = $this->app->make(HttpKernel::class)->handle($request);

        return $this->createTestResponse($response, $request);
    }

    protected function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function userWithRole(string $role): User
    {
        return User::factory()->role($role)->create();
    }

    /** Creates content through the domain service as the system actor. */
    protected function makeContent(array $data = [], ?User $author = null): Content
    {
        $data += [
            'type' => 'post',
            'status' => 'published',
            'translations' => ['vi' => ['title' => 'Bài viết thử nghiệm', 'body' => '<p>Nội dung</p>']],
        ];

        $content = app(ContentService::class)->save(new Content, $data, null);
        if ($author) {
            $content->forceFill(['author_id' => $author->id])->save();
        }

        return $content->fresh();
    }
}
