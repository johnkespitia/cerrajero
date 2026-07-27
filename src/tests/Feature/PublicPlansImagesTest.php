<?php

namespace Tests\Feature;

use App\Models\DayPassCapacity;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServicePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPlansImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Plans image tests require MySQL-compatible schema.');
        }
    }

    public function test_public_plans_includes_room_type_images(): void
    {
        $roomType = RoomType::create([
            'name' => 'Suite con vista',
            'code' => 'SVT',
            'description' => 'Habitación amplia con balcón.',
            'image_url' => '/images/room-suite-cover.jpg',
            'gallery' => [
                '/images/room-suite-1.jpg',
                '/images/room-suite-2.jpg',
            ],
            'default_capacity' => 4,
            'max_capacity' => 6,
            'base_price' => 450000,
            'active' => true,
        ]);

        Room::create([
            'room_type_id' => $roomType->id,
            'number' => '301',
            'name' => 'Suite 301',
            'status' => 'available',
            'active' => true,
            'capacity' => 4,
            'max_capacity' => 6,
            'room_price' => 450000,
        ]);

        DayPassCapacity::updateOrCreate(
            ['date' => now()->format('Y-m-d')],
            [
                'max_capacity' => 100,
                'consumed_capacity' => 0,
                'adult_price' => 85000,
                'child_price' => 42000,
            ]
        );

        $response = $this->getJson('/api/public/booking/plans');

        $response->assertOk()
            ->assertJsonPath('lodging.room_types.0.id', $roomType->id)
            ->assertJsonPath('lodging.room_types.0.image_url', '/images/room-suite-cover.jpg')
            ->assertJsonPath('lodging.room_types.0.gallery.0', '/images/room-suite-1.jpg')
            ->assertJsonPath('lodging.room_types.0.gallery.1', '/images/room-suite-2.jpg');
    }

    public function test_public_plans_includes_package_room_type_images(): void
    {
        $roomType = RoomType::create([
            'name' => 'Familiar',
            'code' => 'FAM',
            'description' => 'Ideal para familias.',
            'image_url' => '/images/room-family-cover.jpg',
            'gallery' => ['/images/room-family-gallery.jpg'],
            'default_capacity' => 4,
            'max_capacity' => 4,
            'base_price' => 280000,
            'active' => true,
        ]);

        ServicePackage::create([
            'name' => 'Plan fin de semana',
            'description' => 'Hospedaje familiar con servicios incluidos.',
            'room_type_id' => $roomType->id,
            'status' => 'active',
        ]);

        DayPassCapacity::updateOrCreate(
            ['date' => now()->format('Y-m-d')],
            [
                'max_capacity' => 100,
                'consumed_capacity' => 0,
                'adult_price' => 85000,
                'child_price' => 42000,
            ]
        );

        $response = $this->getJson('/api/public/booking/plans');

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Plan fin de semana',
            ])
            ->assertJsonPath('lodging.packages.0.room_type.image_url', '/images/room-family-cover.jpg')
            ->assertJsonPath('lodging.packages.0.room_type.gallery.0', '/images/room-family-gallery.jpg');
    }

    public function test_public_room_types_includes_image_fields(): void
    {
        RoomType::create([
            'name' => 'Estándar',
            'code' => 'EST',
            'image_url' => 'https://cdn.example.com/rooms/estandar.jpg',
            'gallery' => ['https://cdn.example.com/rooms/estandar-2.jpg'],
            'default_capacity' => 2,
            'max_capacity' => 2,
            'base_price' => 180000,
            'active' => true,
        ]);

        $response = $this->getJson('/api/public/booking/room-types');

        $response->assertOk()
            ->assertJsonStructure([
                [
                    'id',
                    'name',
                    'code',
                    'image_url',
                    'gallery',
                ],
            ])
            ->assertJsonPath('0.image_url', 'https://cdn.example.com/rooms/estandar.jpg')
            ->assertJsonPath('0.gallery.0', 'https://cdn.example.com/rooms/estandar-2.jpg');
    }
}
