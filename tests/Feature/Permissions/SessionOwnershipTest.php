<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SessionOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    public function test_session_from_another_user_cannot_authorize_current_user(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $this->actingAs($other)->withSession(['domain_session_id' => $this->sessionFor($owner)]);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertNull(session('domain_session_id'));
    }

    public function test_disabling_user_invalidates_existing_session(): void
    {
        $actor = $this->user();
        $id = $this->sessionFor($actor);
        $actor->forceFill(['is_active' => false])->save();
        $this->actingAs($actor)->withSession(['domain_session_id' => $id]);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_matching_active_user_session_remains_valid(): void
    {
        $actor = $this->user();
        $this->actingAs($actor)->withSession(['domain_session_id' => $this->sessionFor($actor)]);
        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($actor);
    }

    public function test_camera_policy_is_enabled_only_on_scanning_pages(): void
    {
        $this->get('/login')->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $actor = $this->user();
        $this->actingAs($actor)->withSession(['domain_session_id' => $this->sessionFor($actor)]);
        $this->get('/asset-scanner')->assertOk()->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()');
        $this->get('/dashboard')->assertOk()->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    }

    private function user(): User
    {
        return User::withoutGlobalScopes()->create([
            'username' => 'session-audit-'.Str::uuid(), 'name' => 'Session audit',
            'email' => Str::uuid().'@example.test', 'password' => bcrypt('test-only'),
            'is_active' => true, 'is_super_admin' => true,
        ]);
    }

    private function sessionFor(User $user): string
    {
        $id = (string) Str::uuid();
        DB::table('login_sessions')->insert([
            'session_id' => $id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'Session audit', 'computer_name' => 'test',
            'started_at' => now(), 'last_activity_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }
}
