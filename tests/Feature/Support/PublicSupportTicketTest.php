<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Http\Middleware\EnsureActiveLoginSession;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class PublicSupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_exposes_accessible_support_widget(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('پشتیبانی و فروش')
            ->assertSee('supportPanel')
            ->assertSee(route('support-tickets.store'), false);
    }

    public function test_guest_can_create_ticket_and_receive_tracking_code(): void
    {
        $response = $this->postJson(route('support-tickets.store'), [
            'type' => 'demo',
            'name' => 'محمد احمدی',
            'mobile' => '۰۹۱۲ ۱۲۳ ۴۵۶۷',
            'organization' => 'شرکت نمونه',
            'email' => 'INFO@EXAMPLE.COM',
            'message' => 'برای سازمان خود درخواست نمایش و مشاوره دارم.',
            'privacy' => '1',
            'source_url' => 'https://amvalyar.ir/',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'درخواست شما با موفقیت ثبت شد.')
            ->assertJsonStructure(['tracking_code']);

        $this->assertDatabaseHas('support_tickets', [
            'type' => 'demo',
            'mobile' => '09121234567',
            'email' => 'info@example.com',
            'status' => 'new',
        ]);

        $this->assertMatchesRegularExpression(
            '/^AY-\d{6}-[A-Z0-9]{6}$/',
            SupportTicket::query()->sole()->tracking_code
        );
    }

    public function test_invalid_or_bot_submission_is_rejected(): void
    {
        $this->postJson(route('support-tickets.store'), [
            'type' => 'unknown',
            'name' => 'م',
            'mobile' => '123',
            'message' => 'کوتاه',
            'website' => 'spam.example',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'name', 'mobile', 'message', 'privacy', 'website']);

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_support_inbox_is_protected_by_active_session_and_super_admin_middleware(): void
    {
        $route = Route::getRoutes()->getByName('support-tickets.index');

        self::assertNotNull($route);
        self::assertContains(
            EnsureActiveLoginSession::class,
            $route->gatherMiddleware()
        );
        self::assertContains(
            EnsureSuperAdmin::class,
            $route->gatherMiddleware()
        );
    }
}
