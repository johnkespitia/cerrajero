<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\SiteContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_site_content_returns_defaults_when_not_configured(): void
    {
        $response = $this->getJson('/api/public/site/content');

        $response->assertOk()
            ->assertJsonStructure([
                'hero_slides',
                'home_video_url',
                'home_video_poster_url',
                'about_gallery',
            ]);

        $this->assertNotEmpty($response->json('hero_slides'));
        $this->assertNotEmpty($response->json('about_gallery'));
    }

    public function test_public_site_content_returns_saved_settings(): void
    {
        SiteSetting::setJson(SiteContentService::KEY_HERO_SLIDES, [
            [
                'image_url' => '/images/test-banner.jpg',
                'title' => 'Test',
                'plan_title' => 'Incluye',
                'plan_items' => ['Item 1'],
                'cta_text' => 'Reservar',
                'cta_link' => '/reservar',
            ],
        ]);
        SiteSetting::set(SiteContentService::KEY_HOME_VIDEO_URL, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $response = $this->getJson('/api/public/site/content');

        $response->assertOk()
            ->assertJsonPath('hero_slides.0.title', 'Test')
            ->assertJsonPath('home_video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    }

    public function test_public_site_content_returns_about_gallery(): void
    {
        SiteSetting::setJson(SiteContentService::KEY_ABOUT_GALLERY, [
            [
                'image_url' => '/images/galeria-1.jpg',
                'caption' => 'Piscina principal',
            ],
            [
                'image_url' => '/images/galeria-2.jpg',
                'caption' => 'Zona de descanso',
            ],
        ]);

        $response = $this->getJson('/api/public/site/content');

        $response->assertOk()
            ->assertJsonPath('about_gallery.0.image_url', '/images/galeria-1.jpg')
            ->assertJsonPath('about_gallery.0.caption', 'Piscina principal')
            ->assertJsonPath('about_gallery.1.caption', 'Zona de descanso');
    }
}
