<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class WebSecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_responses_include_baseline_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader(
                'Permissions-Policy',
                'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
            );
    }

    public function test_login_is_rate_limited_after_five_failed_requests(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'username' => 'missing-user',
                'password' => 'invalid-password',
            ])->assertRedirect('/login');
        }

        $this->post('/login', [
            'username' => 'missing-user',
            'password' => 'invalid-password',
        ])->assertTooManyRequests();
    }

    public function test_login_page_uses_public_branding_without_dead_password_link(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('اموال‌یار')
            ->assertSee('برای بازیابی دسترسی با مدیر سامانه سازمان خود تماس بگیرید.')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('کیمیا پلی‌استر');
    }

    public function test_successful_login_rotates_the_session_identifier(): void
    {
        $user = User::query()->where('is_super_admin', true)->firstOrFail();
        $user->forceFill([
            'is_active' => true,
            'password' => Hash::make('temporary-test-password'),
        ])->save();

        $this->get('/login')->assertOk();
        $originalSessionId = session()->getId();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'temporary-test-password',
        ])->assertRedirect('/dashboard');

        $this->assertNotSame($originalSessionId, session()->getId());
        $this->assertAuthenticatedAs($user);
    }
}
