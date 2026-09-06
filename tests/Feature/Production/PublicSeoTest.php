<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    public function test_public_home_exposes_canonical_metadata_and_site_identity(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('<title>اموال‌یار | نرم‌افزار مدیریت اموال و دارایی‌های سازمانی</title>', false)
            ->assertSee('<link rel="canonical" href="https://amvalyar.ir/">', false)
            ->assertSee('name="robots" content="index, follow, max-image-preview:large"', false)
            ->assertSee('"\\u0040type":"WebSite"', false)
            ->assertSee('"name":"اموال‌یار"', false);
    }

    public function test_login_is_not_indexable_and_uses_the_original_brand_mark(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false)
            ->assertSee('branding/amvalyar-mark-original.svg', false);
    }

    public function test_robots_and_sitemap_reference_the_canonical_domain(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));
        $sitemap = file_get_contents(public_path('sitemap.xml'));

        $this->assertIsString($robots);
        $this->assertStringContainsString('Allow: /', $robots);
        $this->assertStringContainsString('Disallow: /login', $robots);
        $this->assertStringContainsString('Sitemap: https://amvalyar.ir/sitemap.xml', $robots);

        $this->assertIsString($sitemap);
        $this->assertStringContainsString('<loc>https://amvalyar.ir/</loc>', $sitemap);
        $this->assertNotFalse(simplexml_load_string($sitemap));
    }
}
