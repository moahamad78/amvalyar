<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class BootstrapDemoAdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_command_creates_first_super_admin_from_configuration(): void
    {
        User::query()->where('is_super_admin', true)->update([
            'is_super_admin' => false,
        ]);

        config()->set('demo.admin', [
            'username' => 'demo-admin',
            'name' => 'Demo Admin',
            'email' => 'demo@example.com',
            'password' => 'a-strong-demo-password',
        ]);

        $this->artisan('app:bootstrap-demo-admin')->assertSuccessful();

        $user = User::query()->where('username', 'demo-admin')->sole();

        $this->assertSame('demo-admin', $user->username);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->isActive());
        $this->assertTrue(Hash::check('a-strong-demo-password', $user->password));
    }

    public function test_command_never_overwrites_an_existing_super_admin(): void
    {
        $existing = User::query()->where('is_super_admin', true)->firstOrFail();
        $userCount = User::query()->count();
        $existingUsername = $existing->username;

        config()->set('demo.admin', [
            'username' => 'replacement-admin',
            'name' => 'Replacement Admin',
            'email' => 'replacement@example.com',
            'password' => 'a-strong-demo-password',
        ]);

        $this->artisan('app:bootstrap-demo-admin')->assertSuccessful();

        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($existingUsername, $existing->fresh()->username);
    }
}
