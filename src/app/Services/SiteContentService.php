<?php

namespace App\Services;

use App\Models\SiteSetting;

class SiteContentService
{
    public const KEY_HERO_SLIDES = 'hero_slides';
    public const KEY_HOME_VIDEO_URL = 'home_video_url';
    public const KEY_HOME_VIDEO_POSTER = 'home_video_poster_url';
    public const KEY_ABOUT_GALLERY = 'about_gallery';
    public const KEY_CONTACT_ADDRESS = 'contact_address';
    public const KEY_CONTACT_SLA = 'contact_sla';
    public const KEY_POLICIES = 'policies';

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
                'plan_title' => 'Tarifa incluye',
                'plan_items' => [
                    'Habitación',
                    'Acceso a piscina',
                    'Acceso a zonas verdes',
                    'Parqueadero',
                ],
                'cta_text' => '¡Reserve Ahora!',
                'cta_link' => '/reservar',
            ],
            [
                'image_url' => '/images/panoramic2.a8ffc854.jpg',
                'title' => 'Pasadía',
                'plan_title' => 'Tarifa incluye',
                'plan_items' => [
                    'Acceso a piscina',
                    'Acceso a zonas verdes',
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
            'contact' => [
                'address' => SiteSetting::get(self::KEY_CONTACT_ADDRESS, 'Vereda La Campana, Cocorná, Antioquia, Colombia'),
                'sla' => SiteSetting::get(self::KEY_CONTACT_SLA, 'Respuesta en máximo 24 horas hábiles por correo o WhatsApp.'),
            ],
            'policies' => SiteSetting::getJson(self::KEY_POLICIES, $this->defaultPolicies()),
        ];
    }

    public function defaultPolicies(): array
    {
        return [
            [
                'title' => 'Política de reserva',
                'items' => [
                    'Las reservas están sujetas a disponibilidad.',
                    'Se requiere confirmación por parte del equipo de Campo Verde.',
                    'Los precios son por persona por noche salvo indicación contraria.',
                    'El mínimo facturable corresponde a la capacidad mínima del tipo de habitación.',
                ],
            ],
            [
                'title' => 'Política de pago',
                'items' => [
                    'El depósito o pago completo debe ser confirmado antes del check-in.',
                    'Los comprobantes de pago deben ser enviados por correo o WhatsApp.',
                    'No se aceptan devoluciones una vez confirmada la reserva.',
                ],
            ],
            [
                'title' => 'Check-in / Check-out',
                'items' => [
                    'Check-in: a partir de las 15:00.',
                    'Check-out: antes de las 12:00 del día siguiente.',
                    'Sujeto a disponibilidad para early check-in o late check-out.',
                ],
            ],
            [
                'title' => 'Normas de la propiedad',
                'items' => [
                    'Está prohibido el uso de equipos de sonido después de las 22:00.',
                    'No se permite el ingreso de mascotas salvo autorización previa.',
                    'Se responsabiliza al huésped por los daños a las instalaciones.',
                ],
            ],
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

        if (array_key_exists('contact', $payload)) {
            $contact = $payload['contact'];
            if (array_key_exists('address', $contact)) {
                SiteSetting::set(
                    self::KEY_CONTACT_ADDRESS,
                    (string) $contact['address'],
                    'Dirección del centro vacacional'
                );
            }
            if (array_key_exists('sla', $contact)) {
                SiteSetting::set(
                    self::KEY_CONTACT_SLA,
                    (string) $contact['sla'],
                    'Tiempo de respuesta al cliente'
                );
            }
        }

        if (array_key_exists('policies', $payload)) {
            SiteSetting::setJson(
                self::KEY_POLICIES,
                $payload['policies'],
                'Políticas del centro vacacional'
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

        if (SiteSetting::where('key', self::KEY_CONTACT_ADDRESS)->doesntExist()) {
            SiteSetting::set(
                self::KEY_CONTACT_ADDRESS,
                'Vereda La Campana, Cocorná, Antioquia, Colombia',
                'Dirección del centro vacacional'
            );
        }

        if (SiteSetting::where('key', self::KEY_CONTACT_SLA)->doesntExist()) {
            SiteSetting::set(
                self::KEY_CONTACT_SLA,
                'Respuesta en máximo 24 horas hábiles por correo o WhatsApp.',
                'Tiempo de respuesta al cliente'
            );
        }

        if (SiteSetting::where('key', self::KEY_POLICIES)->doesntExist()) {
            SiteSetting::setJson(
                self::KEY_POLICIES,
                $this->defaultPolicies(),
                'Políticas del centro vacacional'
            );
        }
    }
}
