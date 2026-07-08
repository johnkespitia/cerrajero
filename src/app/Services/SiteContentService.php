<?php

namespace App\Services;

use App\Models\SiteSetting;

class SiteContentService
{
    public const KEY_HERO_SLIDES = 'hero_slides';
    public const KEY_HOME_VIDEO_URL = 'home_video_url';
    public const KEY_HOME_VIDEO_POSTER = 'home_video_poster_url';
    public const KEY_ABOUT_GALLERY = 'about_gallery';

    public function defaultAboutGallery(): array
    {
        return [
            ['image_url' => '/images/panoramic1.c8f24303.jpg', 'caption' => 'Instalaciones Campo Verde'],
            ['image_url' => '/images/panoramic2.a8ffc854.jpg', 'caption' => 'Zonas verdes'],
            ['image_url' => '/images/panoramic3.7d6caa6c.jpg', 'caption' => 'Vista panorámica'],
            ['image_url' => '/images/Imag_campo_4.c13fb605.jpg', 'caption' => 'Espacios de convivencia'],
            ['image_url' => '/images/Imag_campo_5.9646e4cb.jpg', 'caption' => 'Actividades al aire libre'],
            ['image_url' => '/images/Imag_campo_6.f80886b7.jpg', 'caption' => 'Nuestras instalaciones'],
        ];
    }

    public function defaultHeroSlides(): array
    {
        return [
            [
                'image_url' => '/images/panoramic1.c8f24303.jpg',
                'title' => 'Hospedaje',
                'plan_title' => 'Plan incluye',
                'plan_items' => [
                    'Habitación con camas',
                    'Desayuno',
                    'Almuerzo',
                    'Cena',
                    'Piscina',
                    'Parqueadero',
                ],
                'cta_text' => '¡Reserve Ahora!',
                'cta_link' => '/reservar',
            ],
            [
                'image_url' => '/images/panoramic2.a8ffc854.jpg',
                'title' => 'Pasadía',
                'plan_title' => 'Plan incluye',
                'plan_items' => [
                    'Almuerzo',
                    'Cena',
                    'Piscina',
                    'Parqueadero',
                ],
                'cta_text' => '¡Reserve Ahora!',
                'cta_link' => '/reservar?tipo=day_pass',
            ],
        ];
    }

    public function getPublicContent(): array
    {
        return [
            'hero_slides' => $this->getHeroSlides(),
            'home_video_url' => SiteSetting::get(self::KEY_HOME_VIDEO_URL, ''),
            'home_video_poster_url' => SiteSetting::get(
                self::KEY_HOME_VIDEO_POSTER,
                '/images/panoramic1.c8f24303.jpg'
            ),
            'about_gallery' => $this->getAboutGallery(),
        ];
    }

    public function getAdminContent(): array
    {
        return $this->getPublicContent();
    }

    public function getHeroSlides(): array
    {
        $slides = SiteSetting::getJson(self::KEY_HERO_SLIDES, $this->defaultHeroSlides());

        return array_values(array_filter(array_map(function ($slide) {
            if (! is_array($slide)) {
                return null;
            }

            $imageUrl = trim((string) ($slide['image_url'] ?? ''));
            if ($imageUrl === '') {
                return null;
            }

            return [
                'image_url' => $imageUrl,
                'title' => (string) ($slide['title'] ?? ''),
                'plan_title' => (string) ($slide['plan_title'] ?? 'Plan incluye'),
                'plan_items' => array_values(array_filter(array_map(
                    'strval',
                    $slide['plan_items'] ?? []
                ))),
                'cta_text' => (string) ($slide['cta_text'] ?? '¡Reserve Ahora!'),
                'cta_link' => (string) ($slide['cta_link'] ?? '/reservar'),
            ];
        }, $slides)));
    }

    public function getAboutGallery(): array
    {
        $items = SiteSetting::getJson(self::KEY_ABOUT_GALLERY, $this->defaultAboutGallery());

        return array_values(array_filter(array_map(function ($item) {
            if (! is_array($item)) {
                return null;
            }

            $imageUrl = trim((string) ($item['image_url'] ?? ''));
            if ($imageUrl === '') {
                return null;
            }

            return [
                'image_url' => $imageUrl,
                'caption' => (string) ($item['caption'] ?? ''),
            ];
        }, $items)));
    }

    public function updateContent(array $payload): array
    {
        if (array_key_exists('hero_slides', $payload)) {
            SiteSetting::setJson(
                self::KEY_HERO_SLIDES,
                $payload['hero_slides'],
                'Slides del banner principal del sitio web'
            );
        }

        if (array_key_exists('home_video_url', $payload)) {
            SiteSetting::set(
                self::KEY_HOME_VIDEO_URL,
                (string) $payload['home_video_url'],
                'URL del video de bienvenida (YouTube o similar)'
            );
        }

        if (array_key_exists('home_video_poster_url', $payload)) {
            SiteSetting::set(
                self::KEY_HOME_VIDEO_POSTER,
                (string) $payload['home_video_poster_url'],
                'Imagen de portada del video de bienvenida'
            );
        }

        if (array_key_exists('about_gallery', $payload)) {
            SiteSetting::setJson(
                self::KEY_ABOUT_GALLERY,
                $payload['about_gallery'],
                'Galería de fotos de la página Quienes somos'
            );
        }

        return $this->getPublicContent();
    }

    public function seedDefaults(): void
    {
        if (SiteSetting::where('key', self::KEY_HERO_SLIDES)->doesntExist()) {
            SiteSetting::setJson(
                self::KEY_HERO_SLIDES,
                $this->defaultHeroSlides(),
                'Slides del banner principal del sitio web'
            );
        }

        if (SiteSetting::where('key', self::KEY_ABOUT_GALLERY)->doesntExist()) {
            SiteSetting::setJson(
                self::KEY_ABOUT_GALLERY,
                $this->defaultAboutGallery(),
                'Galería de fotos de la página Quienes somos'
            );
        }
    }
}
