<?php

namespace Database\Seeders;

use App\Models\AdditionalService;
use App\Models\DayPassCapacity;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServicePackage;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HospitalityDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (!Room::where('active', true)->exists()) {
            $this->seedRoomsAndDayPass();
        } else {
            $this->command?->info('HospitalityDemoSeeder: habitaciones ya existen, se omite inventario.');
        }

        $this->seedPlansCatalog();
        $this->seedSiteContent();
    }

    protected function seedSiteContent(): void
    {
        app(\App\Services\SiteContentService::class)->seedDefaults();
        $this->syncRoomTypeImages();
    }

    protected function syncRoomTypeImages(): void
    {
        $imagesByCode = [
            'EST' => '/images/panoramic1.c8f24303.jpg',
            'FAM' => '/images/panoramic2.a8ffc854.jpg',
            'SUITE' => '/images/panoramic1.c8f24303.jpg',
        ];

        foreach ($imagesByCode as $code => $imageUrl) {
            RoomType::where('code', $code)
                ->whereNull('image_url')
                ->update(['image_url' => $imageUrl]);
        }
    }

    protected function seedRoomsAndDayPass(): void
    {
        $this->command?->info('HospitalityDemoSeeder: creando tipos de habitación y habitaciones de demo...');

        $types = [
            [
                'code' => 'EST',
                'name' => 'Estándar',
                'description' => 'Habitación cómoda para parejas o viajeros individuales.',
                'default_capacity' => 2,
                'max_capacity' => 2,
                'base_price' => 180000,
                'features' => ['Cama doble o sencillas', 'Baño privado', 'WiFi', 'TV', 'Aire acondicionado'],
                'rooms' => [
                    ['number' => '101', 'name' => 'Habitación 101'],
                    ['number' => '102', 'name' => 'Habitación 102'],
                    ['number' => '103', 'name' => 'Habitación 103'],
                    ['number' => '104', 'name' => 'Habitación 104'],
                ],
            ],
            [
                'code' => 'FAM',
                'name' => 'Familiar',
                'description' => 'Espacio amplio ideal para familias.',
                'default_capacity' => 4,
                'max_capacity' => 4,
                'base_price' => 280000,
                'features' => ['Camas múltiples', 'Baño privado', 'WiFi', 'TV', 'Zona de descanso'],
                'rooms' => [
                    ['number' => '201', 'name' => 'Habitación 201'],
                    ['number' => '202', 'name' => 'Habitación 202'],
                    ['number' => '203', 'name' => 'Habitación 203'],
                ],
            ],
            [
                'code' => 'SUITE',
                'name' => 'Suite',
                'description' => 'Suite premium con mayor confort.',
                'default_capacity' => 4,
                'max_capacity' => 6,
                'base_price' => 450000,
                'features' => ['Ambiente premium', 'Baño amplio', 'Minibar', 'Balcón o vista', 'Servicio prioritario'],
                'rooms' => [
                    ['number' => '301', 'name' => 'Suite 301'],
                    ['number' => '302', 'name' => 'Suite 302'],
                ],
            ],
        ];

        foreach ($types as $typeData) {
            $rooms = $typeData['rooms'];
            unset($typeData['rooms']);

            $roomType = RoomType::updateOrCreate(
                ['code' => $typeData['code']],
                array_merge($typeData, ['active' => true])
            );

            foreach ($rooms as $roomData) {
                Room::updateOrCreate(
                    [
                        'room_type_id' => $roomType->id,
                        'number' => $roomData['number'],
                    ],
                    [
                        'name' => $roomData['name'],
                        'capacity' => $roomType->default_capacity,
                        'max_capacity' => $roomType->max_capacity,
                        'room_price' => $roomType->base_price,
                        'status' => 'available',
                        'active' => true,
                        'description' => "Demo {$roomType->name}",
                        'amenities' => ['wifi', 'tv', 'aire_acondicionado'],
                    ]
                );
            }
        }

        $today = Carbon::today();
        for ($i = 0; $i < 90; $i++) {
            $date = $today->copy()->addDays($i)->format('Y-m-d');
            DayPassCapacity::updateOrCreate(
                ['date' => $date],
                [
                    'max_capacity' => 200,
                    'consumed_capacity' => 0,
                    'adult_price' => 80000,
                    'child_price' => 40000,
                ]
            );
        }

        $roomCount = Room::where('active', true)->count();
        $typeCount = RoomType::where('active', true)->count();
        $this->command?->info("HospitalityDemoSeeder: {$typeCount} tipos, {$roomCount} habitaciones, 90 días de pasadía.");
    }

    protected function seedPlansCatalog(): void
    {
        if (AdditionalService::exists()) {
            $this->command?->info('HospitalityDemoSeeder: catálogo de planes ya existe, se omite.');

            return;
        }

        $this->command?->info('HospitalityDemoSeeder: creando servicios y paquetes de demo...');

        $dayPassServices = [
            [
                'name' => 'Acceso a piscinas',
                'description' => 'Uso de piscina principal y zonas húmedas durante el día.',
                'price' => 0,
                'billing_type' => 'one_time',
                'applies_to' => 'day_pass',
                'is_per_guest' => false,
            ],
            [
                'name' => 'Zonas verdes y juegos',
                'description' => 'Acceso a áreas verdes, juegos infantiles y zonas de descanso.',
                'price' => 0,
                'billing_type' => 'one_time',
                'applies_to' => 'day_pass',
                'is_per_guest' => false,
            ],
            [
                'name' => 'Restaurante (consumo)',
                'description' => 'Acceso al restaurante. Alimentos y bebidas con tarifa independiente.',
                'price' => 0,
                'billing_type' => 'one_time',
                'applies_to' => 'day_pass',
                'is_per_guest' => false,
            ],
            [
                'name' => 'Almuerzo tipo bandeja',
                'description' => 'Bandeja paisa o menú del día en restaurante.',
                'price' => 45000,
                'billing_type' => 'one_time',
                'applies_to' => 'day_pass',
                'is_per_guest' => true,
                'is_food_service' => true,
                'meal_type' => 'lunch',
            ],
            [
                'name' => 'Parrilla / BBQ',
                'description' => 'Reserva de zona BBQ con parrilla (capacidad limitada).',
                'price' => 35000,
                'billing_type' => 'one_time',
                'applies_to' => 'day_pass',
                'is_per_guest' => false,
            ],
            [
                'name' => 'Kayak o actividad acuática',
                'description' => 'Actividad recreativa en lago o zona acuática (sujeto a disponibilidad).',
                'price' => 25000,
                'billing_type' => 'one_time',
                'applies_to' => 'day_pass',
                'is_per_guest' => true,
            ],
        ];

        $lodgingServices = [
            [
                'name' => 'Desayuno incluido',
                'description' => 'Desayuno buffet en restaurante por persona y por noche.',
                'price' => 25000,
                'billing_type' => 'per_day',
                'applies_to' => 'room',
                'is_per_guest' => true,
                'is_food_service' => true,
                'meal_type' => 'breakfast',
            ],
            [
                'name' => 'Cena romántica',
                'description' => 'Cena especial para pareja en restaurante.',
                'price' => 120000,
                'billing_type' => 'one_time',
                'applies_to' => 'both',
                'is_per_guest' => false,
                'is_food_service' => true,
                'meal_type' => 'dinner',
            ],
            [
                'name' => 'Late check-out',
                'description' => 'Salida extendida hasta las 3:00 p.m. (sujeto a disponibilidad).',
                'price' => 50000,
                'billing_type' => 'one_time',
                'applies_to' => 'room',
                'is_per_guest' => false,
            ],
        ];

        $createdServices = [];
        foreach (array_merge($dayPassServices, $lodgingServices) as $serviceData) {
            $createdServices[$serviceData['name']] = AdditionalService::create(
                array_merge(['status' => 'active'], $serviceData)
            );
        }

        $estandar = RoomType::where('code', 'EST')->first();
        $familiar = RoomType::where('code', 'FAM')->first();

        if ($estandar) {
            $package = ServicePackage::create([
                'name' => 'Plan Estándar Plus',
                'description' => 'Hospedaje en habitación estándar con desayuno incluido.',
                'room_type_id' => $estandar->id,
                'status' => 'active',
            ]);
            $package->additionalServices()->sync([
                $createdServices['Desayuno incluido']->id,
            ]);
        }

        if ($familiar) {
            $package = ServicePackage::create([
                'name' => 'Plan Familiar',
                'description' => 'Habitación familiar con desayuno para toda la familia.',
                'room_type_id' => $familiar->id,
                'status' => 'active',
            ]);
            $package->additionalServices()->sync([
                $createdServices['Desayuno incluido']->id,
            ]);
        }

        $this->command?->info('HospitalityDemoSeeder: catálogo de planes creado.');
        $this->syncRoomTypeFeatures();
    }

    protected function syncRoomTypeFeatures(): void
    {
        $featuresByCode = [
            'EST' => ['Cama doble o sencillas', 'Baño privado', 'WiFi', 'TV', 'Aire acondicionado'],
            'FAM' => ['Camas múltiples', 'Baño privado', 'WiFi', 'TV', 'Zona de descanso'],
            'SUITE' => ['Ambiente premium', 'Baño amplio', 'Minibar', 'Balcón o vista', 'Servicio prioritario'],
        ];

        foreach ($featuresByCode as $code => $features) {
            RoomType::where('code', $code)->update(['features' => $features]);
        }
    }
}
