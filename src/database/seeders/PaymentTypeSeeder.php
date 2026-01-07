<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $paymentTypes = [
            [
                'name' => 'Efectivo',
                'active' => true,
                'credit' => false,
                'calculator' => true
            ],
            [
                'name' => 'Tarjeta',
                'active' => true,
                'credit' => false,
                'calculator' => true
            ],
            [
                'name' => 'Transferencia',
                'active' => true,
                'credit' => false,
                'calculator' => true
            ],
            [
                'name' => 'Cheque',
                'active' => true,
                'credit' => false,
                'calculator' => true
            ],
            [
                'name' => 'Otro',
                'active' => true,
                'credit' => false,
                'calculator' => true
            ]
        ];

        foreach ($paymentTypes as $paymentTypeData) {
            PaymentType::firstOrCreate(
                ['name' => $paymentTypeData['name']],
                $paymentTypeData
            );
        }

        $this->command->info('✅ Tipos de pago creados exitosamente.');
    }
}
