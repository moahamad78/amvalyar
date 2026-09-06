<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_root_renders_the_public_product_page(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('نرم‌افزار مدیریت اموال و دارایی‌های سازمانی')
            ->assertSee('https://amvalyar.ir/', false)
            ->assertSee('branding/amvalyar-logo-original.svg', false);
    }
}
