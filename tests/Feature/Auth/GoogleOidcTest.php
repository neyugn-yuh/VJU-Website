<?php

namespace Tests\Feature\Auth;

use App\Domain\User\GoogleOidcProvider;
use App\Domain\User\UserProvisioner;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleOidcTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function claims(array $overrides = []): array
    {
        return $overrides + ['sub' => 'google-123', 'email' => 'nguyen.van.a@vju.ac.vn', 'email_verified' => true, 'hd' => 'vju.ac.vn', 'name' => 'Nguyễn Văn A', 'picture' => 'https://example.com/a.png'];
    }

    public function test_callback_creates_user_without_any_role(): void
    {
        $user = app(UserProvisioner::class)->fromGoogleClaims($this->claims());

        $this->assertSame('google-123', $user->google_subject);
        $this->assertTrue($user->is_active);
        $this->assertCount(0, $user->roles, 'New OIDC users must never get a role automatically');
    }

    public function test_existing_user_is_linked_by_email(): void
    {
        $existing = User::factory()->role(User::ROLE_EDITOR)->create(['email' => 'nguyen.van.a@vju.ac.vn']);

        $user = app(UserProvisioner::class)->fromGoogleClaims($this->claims());

        $this->assertTrue($user->is($existing));
        $this->assertSame('google-123', $user->fresh()->google_subject);
        $this->assertTrue($user->hasRole(User::ROLE_EDITOR));
    }

    public function test_suspended_user_is_rejected(): void
    {
        User::factory()->role(User::ROLE_ADMIN)->suspended()->create(['email' => 'nguyen.van.a@vju.ac.vn']);

        $this->expectException(AuthorizationException::class);
        app(UserProvisioner::class)->fromGoogleClaims($this->claims());
    }

    public function test_foreign_domain_is_rejected(): void
    {
        $this->expectException(AuthorizationException::class);
        app(UserProvisioner::class)->fromGoogleClaims($this->claims(['email' => 'someone@gmail.com', 'hd' => null]));
    }

    public function test_identity_conflict_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'nguyen.van.a@vju.ac.vn']);
        $user->forceFill(['google_subject' => 'another-subject'])->save();

        $this->expectException(AuthorizationException::class);
        app(UserProvisioner::class)->fromGoogleClaims($this->claims());
    }

    public function test_callback_logs_in_user_with_role_and_audits(): void
    {
        User::factory()->role(User::ROLE_AUTHOR)->create(['email' => 'nguyen.van.a@vju.ac.vn']);
        $this->mock(GoogleOidcProvider::class, fn ($mock) => $mock->shouldReceive('claims')->andReturn($this->claims()));

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/admin');

        $this->assertAuthenticated();
        $this->assertTrue(AuditLog::where('action', 'login')->exists());
    }

    public function test_callback_without_role_does_not_log_in(): void
    {
        $this->mock(GoogleOidcProvider::class, fn ($mock) => $mock->shouldReceive('claims')->andReturn($this->claims()));

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/admin/login')->assertSessionHas('auth_error');

        $this->assertGuest();
    }

    public function test_invalid_state_or_nonce_does_not_log_in(): void
    {
        $this->mock(GoogleOidcProvider::class, fn ($mock) => $mock->shouldReceive('claims')->andThrow(new \RuntimeException('Invalid OIDC nonce.')));

        $this->get('/auth/google/callback?code=x&state=forged')->assertRedirect('/admin/login');

        $this->assertGuest();
    }

    public function test_suspended_user_cannot_access_panel_even_with_session(): void
    {
        $user = User::factory()->role(User::ROLE_ADMIN)->suspended()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }
}
