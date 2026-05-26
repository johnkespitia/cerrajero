<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Seeds the fine-grained permissions used by the Electronic Invoicing (DIAN)
 * routes and UI. Today the `electronic-invoicing/**` API endpoints only
 * enforce `auth:sanctum`; this seeder declares the permission names that
 * upcoming slices (`observability-and-permissions`, `documents-detail-and-downloads`,
 * `fiscal-admin-certificate`, `radian`) will attach to their respective
 * routes through the `permission:<name>,cerrajero` middleware pattern.
 *
 * Placing the declaration here ahead of time lets the wave-A slices run in
 * parallel without racing for who creates the seeder first, and gives the
 * deploy team a single artifact to assign to admin roles before any route
 * gets restricted.
 */
class ElectronicInvoicingPermissionsSeeder extends Seeder
{
    private const GUARD = 'cerrajero';

    /**
     * Source of truth for the EI permission catalogue. Names align with the
     * spec section "Permisos nuevos (en spatie/laravel-permission)" and are
     * the exact tokens used by `permission:<name>,cerrajero` middleware on
     * `/api/electronic-invoicing/**` routes.
     *
     * Extending the catalogue is allowed; renaming an already-released name
     * requires a migration plan because roles may have it assigned.
     */
    public const PERMISSIONS = [
        'electronic_invoicing.list' => 'Listar y consultar documentos electrónicos DIAN',
        'electronic_invoicing.create' => 'Emitir documentos electrónicos (FEV, DEE POS, NC, ND)',
        'electronic_invoicing.retry' => 'Reintentar envío de un documento a DIAN',
        'electronic_invoicing.cancel' => 'Marcar documentos para contingencia o dead letter',
        'electronic_invoicing.admin' => 'Administrar configuración fiscal (certificado, software, resoluciones, promoción)',
        'electronic_invoicing.radian' => 'Emitir eventos RADIAN 030/031/032/033/034',
    ];

    public function run(): void
    {
        $this->command?->info('Creando permisos de Facturación Electrónica DIAN...');

        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['name' => $name, 'guard_name' => self::GUARD]
            );
        }

        $this->command?->info('  ✓ ' . count(self::PERMISSIONS) . ' permisos EI declarados (guard=' . self::GUARD . ').');
    }
}
